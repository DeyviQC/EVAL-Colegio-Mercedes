import { assignment, enrollment, historicalSubmission, period } from './contracts.js';
import type { ReactNode } from 'react';
export type AcademicReadKind='period'|'enrollment'|'assignment'|'history';
const states={planned:'Planificado',active:'Activo',closed:'Cerrado',transferred:'Trasladado'};
function Details({rows}:{rows:readonly (readonly [string,ReactNode])[]}) { return <dl className="grid gap-x-6 gap-y-2 sm:grid-cols-2">{rows.map(([label,value])=><div key={label}><dt className="text-sm text-eval-ink/70">{label}</dt><dd className="whitespace-pre-wrap font-medium">{value}</dd></div>)}</dl>; }
export function AcademicReadView({kind,value}:{kind:AcademicReadKind;value:unknown}) {
  let content:ReactNode;
  try {
    if(kind==='period') {
      const row=period(value);content=<><h2 className="mb-4 text-xl font-semibold whitespace-pre-wrap">{row.name}</h2><Details rows={[
        ['Estado',states[row.state]],['Inicio',row.start_on],['Fin',row.end_on]]}/></>;
    }else if(kind==='enrollment') {
      const row=enrollment(value);content=<><h2 className="mb-4 text-xl font-semibold">Matrícula</h2><Details rows={[
        ['Estado',states[row.state]],['Inicio declarado',row.effective_from],['Fin declarado',row.effective_until??'Sin fin registrado']]}/></>;
    }else if(kind==='assignment') {
      const row=assignment(value);content=<><h2 className="mb-4 text-xl font-semibold">Asignación docente</h2><Details rows={[
        ['Estado',states[row.state]],['Inicio declarado',row.effective_from],['Fin declarado',row.effective_until??'Sin fin registrado']]}/>
        {row.state==='planned'&&<p className="mt-4">Esta planificación no habilita operaciones académicas.</p>}</>;
    }else {
      const row=historicalSubmission(value);const original=row.originalTeachingAssignment;const accepted=row.acceptedUnderEnrollment;
      const acceptedAt=new Intl.DateTimeFormat('es-PE',{dateStyle:'long',timeStyle:'short',timeZone:'America/Lima'}).format(new Date(row.submission.acceptedAt.replace(' ','T')+'Z'));
      content=<><h2 className="mb-4 text-xl font-semibold">Historial de entrega</h2><p className="mb-4">Se conserva el contexto original de la aceptación.</p><Details rows={[
        ['Fecha de aceptación',acceptedAt],
        ['Docente original',original.teacher.displayName],['Área o asignatura original',original.instructionalEntry.displayName],
        ['Período de aceptación',accepted.academicPeriod.displayName],['Grado de aceptación',accepted.grade.displayName],['Sección de aceptación',accepted.section.displayName],
        ['Inicio declarado de la matrícula original',accepted.effectiveFrom],['Fin declarado de la matrícula original',accepted.effectiveUntil??'Sin fin registrado'],
        ['Inicio declarado de la asignación original',original.effectiveFrom],['Fin declarado de la asignación original',original.effectiveUntil??'Sin fin registrado']]}/></>;
    }
  }catch { return <section role="alert" className="rounded-lg border border-red-300 bg-white p-5">No se pudo validar la información recibida.</section>; }
  return <section className="rounded-xl border border-eval-teal/15 bg-white p-6">{content}</section>;
}
