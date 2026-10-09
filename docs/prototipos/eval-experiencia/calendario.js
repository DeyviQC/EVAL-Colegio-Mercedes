'use strict';
// Independent management prototype: memory-only edits, never actual publication.
const originalAdministration=administration;
const calendarTemplate=[
  ['Gestión','2026-03-02','2026-03-13'],['Lectivo','2026-03-16','2026-05-15'],
  ['Gestión','2026-05-18','2026-05-22'],['Lectivo','2026-05-25','2026-07-24'],
  ['Gestión','2026-07-27','2026-08-07'],['Lectivo','2026-08-10','2026-10-09'],
  ['Gestión','2026-10-12','2026-10-16'],['Lectivo','2026-10-19','2026-12-18'],
  ['Gestión','2026-12-21','2026-12-31']
];
let calendarDraft=calendarTemplate.map(block=>[...block]);
administration=function(){return originalAdministration()+`<article class="card body" style="margin-top:20px"><h3>Calendario institucional</h3><p>Revisa bloques lectivos y de gestión. Referencia nacional 2026 pendiente de confirmar con el PAT del colegio.</p><button class="btn primary" data-calendar-open>Revisar calendario · demostración</button></article>`;};
function calendarManagement(){
  document.querySelector('#vista').innerHTML=heading('Calendario institucional','Director / Admin · Año 2026 · Borrador de demostración')+
    '<div class="notice">Cambios temporales en esta página. No se guardan ni publican en EVAL. Las semanas de los cursos continúan mostrando la referencia nacional.</div>'+
    '<p class="muted">La publicación real requerirá el calendario aprobado del colegio. Corregir un calendario publicado creará una nueva revisión y conservará las asociaciones anteriores.</p>'+
    `<form id="calendario-demo"><div class="list">${calendarDraft.map((block,index)=>`<div class="row calendar-row"><strong>Bloque ${index+1} · ${block[0]}</strong><label>Inicio del bloque ${index+1}<input type="date" required name="start-${index}" value="${block[1]}"></label><label>Fin del bloque ${index+1}<input type="date" required name="end-${index}" value="${block[2]}"></label></div>`).join('')}</div><p><label for="calendar-source">Referencia del calendario aprobado</label><input id="calendar-source" required maxlength="255" placeholder="Ejemplo ficticio: PAT 2026 / resolución interna"></p><label class="week-filter"><input id="calendar-confirm" type="checkbox" required> Confirmo la revisión del calendario · simulación</label><p><button class="btn primary" type="submit">Validar borrador · demostración</button> <button class="btn" type="button" data-calendar-reset>Restablecer referencia nacional</button></p><p id="calendar-result" role="status" aria-live="polite"></p></form><p>${button('← Volver a administración','admin')}</p>`;
}
document.addEventListener('click',event=>{
  if(role!=='director')return;
  if(event.target.closest('[data-calendar-open]'))calendarManagement();
  if(event.target.closest('[data-calendar-reset]')){calendarDraft=calendarTemplate.map(block=>[...block]);calendarManagement();document.querySelector('#calendar-result').textContent='Referencia nacional restablecida en la demostración.';}
});
document.addEventListener('submit',event=>{
  if(event.target.id!=='calendario-demo')return;
  event.preventDefault();if(role!=='director')return;
  const form=event.target,output=document.querySelector('#calendar-result');
  const proposed=calendarDraft.map((block,index)=>[block[0],form.elements.namedItem(`start-${index}`).value,form.elements.namedItem(`end-${index}`).value]);
  const invalid=proposed.some((block,index)=>block[1]<'2026-01-01'||block[2]>'2026-12-31'||block[1]>block[2]||(index>0&&block[1]<=proposed[index-1][2]));
  if(invalid){output.textContent='Revisa las fechas: deben pertenecer a 2026, estar ordenadas y no superponerse.';return;}
  if(!form.elements.namedItem('calendar-source').value.trim()){output.textContent='Indica una referencia del calendario.';return;}
  calendarDraft=proposed;output.textContent='Borrador válido en la demostración. No se ha guardado ni publicado ningún calendario.';
});
