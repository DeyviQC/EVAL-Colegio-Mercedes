import { exact } from './contracts.js';
export const sectionLabels={periods:'Períodos académicos',catalog:'Catálogo académico',enrollments:'Estudiantes por aula',assignments:'Organización docente',my_assignments:'Mis cursos',my_enrollments:'Mis cursos'} as const;
export type Section=keyof typeof sectionLabels;
const order=Object.keys(sectionLabels) as Section[];
export function navigationSections(wire:unknown):Section[] {
  const envelope=exact(wire,['data']);const data=exact(envelope.data,['sections']);
  if(!Array.isArray(data.sections))throw new Error('Invalid navigation');
  let prior=-1;
  return data.sections.map(value=>{
    const index=typeof value==='string'?order.indexOf(value as Section):-1;
    if(index<=prior||index<0)throw new Error('Invalid navigation');prior=index;return order[index]!;
  });
}
export type NavigationView={phase:'empty'|'loading'|'ready'|'unavailable';sections:Section[]};
export function createNavigationController(transport:typeof fetch=fetch) {
  let generation=0;let view:NavigationView={phase:'empty',sections:[]};const listeners=new Set<()=>void>();
  const publish=(next:NavigationView)=>{view=next;for(const listener of listeners)listener();};
  function clear(){generation++;publish({phase:'empty',sections:[]});}
  async function refresh(){const current=++generation;publish({phase:'loading',sections:[]});
    try{const response=await transport('/academic/navigation',{credentials:'same-origin',cache:'no-store',redirect:'error'});
      if(!response.ok)throw new Error('Unavailable navigation');const sections=navigationSections(await response.json());
      if(current===generation)publish({phase:'ready',sections});
    }catch{if(current===generation)publish({phase:'unavailable',sections:[]});}
  }
  return {snapshot:()=>view,subscribe:(listener:()=>void)=>{listeners.add(listener);return ()=>{listeners.delete(listener);};},clear,refresh};
}
