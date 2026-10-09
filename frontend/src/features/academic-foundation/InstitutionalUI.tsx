import type {ButtonHTMLAttributes} from 'react';
const crest='/assets/nsm.png';

export function Icon({name='grid',className=''}:{name?:string;className?:string}) {
 const paths:Record<string,string>={home:'m3 10 9-7 9 7v10H7V10m3 10v-7h4v7',user:'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0M4 21v-2a8 8 0 0 1 16 0v2',lock:'M6 10h12v11H6zM8 10V7a4 4 0 0 1 8 0v3',calendar:'M4 5h16v16H4zM8 3v4m8-4v4M4 10h16',bell:'M18 8a6 6 0 0 0-12 0v6l-2 3h16l-2-3zM10 21h4',grid:'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',file:'M5 3h9l5 5v13H5zM14 3v6h5M8 13h8m-8 4h6',chart:'M4 3v18h17M8 17v-5m5 5V8m5 9V5',logout:'M9 4H4v16h5M9 12h12m-4-4 4 4-4 4',menu:'M4 6h16M4 12h16M4 18h16'};
 return <svg className={className} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={paths[name]??paths.grid}/></svg>;
}
export function SchoolBrand({large=false}:{large?:boolean}) {return <div className={large?'eval-school-brand eval-school-brand-large':'eval-school-brand'}><img src={crest} width="140" height="200" alt="Escudo de I.E. Nuestra Señora de las Mercedes"/><div><h1>EVAL</h1><p>I.E. Nuestra Señora de las Mercedes</p><small>Ayacucho · Plataforma educativa institucional</small></div></div>;}
export function NavButton({children,...props}:ButtonHTMLAttributes<HTMLButtonElement>) {
 const label=typeof children==='string'?children:'';
 const name=label==='Inicio'?'home':/Calendario|Período/.test(label)?'calendar':/cuenta|Usuarios|Estudiantes/.test(label)?'user':/Notificaciones/.test(label)?'bell':/Reportes|calificaciones|Calificaciones/.test(label)?'chart':/Material|Biblioteca|Entregas|Actividades|Historial|historial/.test(label)?'file':'grid';
 return <button title={label||undefined} {...props}><Icon name={name}/><span className="eval-nav-label">{children}</span></button>;
}
export function Spinner(){return <svg className="eval-spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="3" opacity=".25"/><path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" strokeWidth="3" strokeLinecap="round"/></svg>;}
