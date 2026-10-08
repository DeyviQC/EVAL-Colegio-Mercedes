import { exact, text } from './contracts.js';
import { AcademicApiError } from './api.js';
export type SessionSnapshot={authenticated:boolean;csrf_token:string};
export function sessionSnapshot(value:unknown):SessionSnapshot {
  const row=exact(value,['authenticated','csrf_token']);
  if(typeof row.authenticated!=='boolean') throw new AcademicApiError('invalid_response',200);
  return {authenticated:row.authenticated,csrf_token:text(row.csrf_token)};
}
/** Opaque cookie is browser-managed; token exists only in this client's memory. */
export function createSessionClient(transport:typeof fetch=fetch) {
  let csrf:string|null=null;
  async function request(path:'session'|'login'|'logout',input?:{login:string;password:string}):Promise<SessionSnapshot> {
    const unsafe=path!=='session';const headers:Record<string,string>={Accept:'application/json'};
    if(unsafe) {
      if(csrf===null)throw new AcademicApiError('session_not_initialized',419);
      headers['X-CSRF-TOKEN']=csrf;headers['Content-Type']='application/json';
    }
    let response:Response;
    try { response=await transport('/auth/'+path,{method:unsafe?'POST':'GET',credentials:'same-origin',cache:'no-store',redirect:'error',headers,...(unsafe?{body:JSON.stringify(input??{})}:{})}); }
    catch { csrf=null;throw new AcademicApiError('transport_unavailable',null,null,unsafe); }
    let wire:unknown;
    try { wire=await response.json(); } catch { csrf=null;throw new AcademicApiError('invalid_response',response.status,null,unsafe); }
    if(!response.ok) {
      csrf=null;
      try {
        const error=exact(wire,['error'],response.status===503?['correlation_id','automatic_retry']:[]);
        const category=text(error.error);if(!/^[a-z_]+$/.test(category))throw new Error();
        const correlation=error.correlation_id===undefined||error.correlation_id===null?null:text(error.correlation_id);
        if(response.status===503&&error.automatic_retry!==false)throw new Error();
        if(correlation!==null&&!/^[a-zA-Z0-9_-]{1,128}$/.test(correlation))throw new Error();
        throw new AcademicApiError(category,response.status,correlation,unsafe&&response.status>=500);
      } catch(error) { if(error instanceof AcademicApiError)throw error;throw new AcademicApiError('invalid_response',response.status,null,unsafe); }
    }
    try { const snapshot=sessionSnapshot(wire);csrf=snapshot.csrf_token;return snapshot; }
    catch { csrf=null;throw new AcademicApiError('invalid_response',response.status,null,unsafe); }
  }
  return {
    refresh:()=>request('session'),
    login:(input:{login:string;password:string})=>{const row=exact(input,['login','password']);return request('login',{login:text(row.login),password:text(row.password)});},
    logout:()=>request('logout'),
    csrfToken:()=>csrf
  };
}
export type SessionClient=ReturnType<typeof createSessionClient>;
export type SessionView={phase:'checking'|'anonymous'|'authenticated'|'busy'|'expired'|'unavailable';message:string};
const checking:SessionView={phase:'checking',message:'Verificando sesión…'};
export function createSessionController(client:SessionClient) {
  let view=checking;let generation=0;let busy=false;const listeners=new Set<()=>void>();
  const publish=(next:SessionView)=>{view=next;for(const listener of listeners)listener();};
  async function run(action:()=>Promise<SessionSnapshot>) {
    if(busy)return;busy=true;const current=++generation;publish({phase:'busy',message:'Un momento…'});
    try {
      const snapshot=await action();if(current!==generation)return;
      publish(snapshot.authenticated?{phase:'authenticated',message:'Sesión iniciada.'}:{phase:'anonymous',message:'Ingresa con tu cuenta institucional.'});
    }catch(error) {
      if(current!==generation)return;
      if(error instanceof AcademicApiError&&(error.status===401||error.status===419))publish({phase:'expired',message:error.category==='invalid_credentials'?'Revisa tu usuario y contraseña.':'Tu sesión debe verificarse nuevamente.'});
      else if(error instanceof AcademicApiError&&error.status===429)publish({phase:'unavailable',message:'Demasiados intentos. Espera antes de volver a ingresar.'});
      else publish({phase:'unavailable',message:'No se pudo confirmar la sesión. Verifica la conexión y consulta de nuevo antes de repetir una acción.'});
    }finally{if(current===generation)busy=false;}
  }
  return {
    snapshot:()=>view,
    subscribe:(listener:()=>void)=>{listeners.add(listener);return ()=>{listeners.delete(listener);};},
    refresh:()=>run(client.refresh),login:(input:{login:string;password:string})=>run(()=>client.login(input)),logout:()=>run(client.logout),
    invalidate:()=>{generation++;busy=false;publish({phase:'expired',message:'Tu sesión debe verificarse nuevamente.'});}
  };
}