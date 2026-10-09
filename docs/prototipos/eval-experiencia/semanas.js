'use strict';
// Synthetic weekly navigation proposal; no application data or writes.
const originalCourseContent = courseContent;
const teachingBlocks=['2026-03-16','2026-05-25','2026-08-10','2026-10-19'];
let selectedBlock=0;
let hideEmptyWeeks=false;
const dateLabel=date=>new Intl.DateTimeFormat('es-PE',{day:'numeric',month:'long',timeZone:'UTC'}).format(date);
function weeklyExamples(){
  const weeks=Array.from({length:9},(_,index)=>{
    const start=new Date(teachingBlocks[selectedBlock]+'T12:00:00Z');start.setUTCDate(start.getUTCDate()+index*7);
    const end=new Date(start);end.setUTCDate(end.getUTCDate()+4);
    const sample=selectedBlock===0&&index<2;
    return {label:`Semana ${selectedBlock*9+index+1}`,dates:`${dateLabel(start)} – ${dateLabel(end)}`,topic:sample?(index===0?'Reconocemos fracciones':'Comparamos fracciones'):'Sin contenido de demostración',material:sample?(index===0?'Guía de fracciones':'Ejemplos para comparar'):null,task:sample?(index===0?'Identificamos partes de un todo':'Resolvemos situaciones cotidianas'):null};
  });
  return [...weeks,{label:'Sin semana',dates:'Recursos generales',topic:'Para consultar cuando lo necesites',material:'Material de apoyo del curso',task:null}];
}
course = function(){
  const teacher=courses.find(item=>item.title===courseTitle)?.teacher;
  const tabs=['Semanas','Materiales','Actividades',role==='estudiante'?'Mis entregas':'Entregas'];
  return `<div class="breadcrumb">${button('← Volver a cursos','cursos')}</div>`+
    heading(courseTitle,`Aula ${courseRoom} · ${teacher} · Contexto 2026`)+
    '<div class="notice">Propuesta visual con ejemplos ficticios. Las semanas agrupan el contenido; no cambian fechas de entrega ni permisos.</div>'+
    `<div class="tabs" role="tablist" aria-label="Contenido del curso">${tabs.map(tab=>`<button role="tab" aria-selected="${courseTab===tab}" class="${courseTab===tab?'active':''}" data-tab="${tab}">${tab}</button>`).join('')}</div><section role="tabpanel" aria-label="${courseTab}">${courseContent()}</section>`;
};
courseContent = function(){
  if(courseTab!=='Semanas')return originalCourseContent();
  return '<p class="muted">Calendario nacional de referencia 2026 · Pendiente de confirmar con el colegio. 36 semanas lectivas; los bloques de gestión no se cuentan como semanas de clase.</p>'+
    `<div class="week-tools"><div><label for="bloque">Bloque lectivo</label><select id="bloque">${teachingBlocks.map((_,index)=>`<option value="${index}" ${selectedBlock===index?'selected':''}>Bloque ${index+1} · Semanas ${index*9+1}–${index*9+9}</option>`).join('')}</select></div><div><label for="ir-semana">Ir a una semana</label><select id="ir-semana"><option value="">Selecciona una semana</option>${Array.from({length:36},(_,index)=>`<option value="${index+1}">Semana ${index+1}</option>`).join('')}</select></div><label class="week-filter"><input id="solo-contenido" type="checkbox" ${hideEmptyWeeks?'checked':''}> Mostrar solo semanas con contenido</label></div><p class="muted">Abre una semana para encontrar juntos sus materiales y tareas.</p>`+
    (hideEmptyWeeks&&!weeklyExamples().some(week=>week.label!=='Sin semana'&&week.material)?'<p class="notice" role="status">Este bloque no tiene contenido de demostración. Puedes mostrar todas sus semanas o consultar los recursos generales.</p>':'')+
    weeklyExamples().filter(week=>!hideEmptyWeeks||week.material||week.task).map((week,index)=>`<details class="week" data-week="${week.label}" ${index===0?'open':''}><summary><span><strong>${week.label}</strong><small>${week.dates} · ${week.topic}</small></span><span class="tag">${week.task?'1 material · 1 tarea':week.material?'1 material':'Sin contenido'}</span></summary>${week.material?`<div class="grid two body"><article class="card body"><span class="tag">Material</span><h3>${week.material}</h3><p>Recurso de ejemplo · PDF</p><button disabled class="disabled-action">Abrir material · demostración</button></article>${week.task?`<article class="card body"><span class="tag">Tarea</span><h3>${week.task}</h3><p>La fecha límite y el estado de entrega se consultarán en cada tarea.</p><button disabled class="disabled-action">Ver tarea · demostración</button></article>`:''}</div>`:'<p class="body muted">No hay materiales ni tareas de ejemplo para esta semana.</p>'}</details>`).join('');
};
document.addEventListener('click',event=>{
  if(event.target.closest('button[data-course]')){selectedBlock=0;hideEmptyWeeks=false;courseTab='Semanas';render();}
});
document.addEventListener('change',event=>{
  if(event.target.id==='bloque'){selectedBlock=Number(event.target.value);render();document.querySelector('#bloque').focus();}
  if(event.target.id==='solo-contenido'){hideEmptyWeeks=event.target.checked;render();document.querySelector('#solo-contenido').focus();}
  if(event.target.id==='ir-semana'&&event.target.value){
    const number=Number(event.target.value);selectedBlock=Math.floor((number-1)/9);hideEmptyWeeks=false;render();
    document.querySelector('#ir-semana').value=String(number);
    const week=document.querySelector(`[data-week="Semana ${number}"]`);week.open=true;
    week.querySelector('summary').focus();week.scrollIntoView({block:'start'});
  }
});
