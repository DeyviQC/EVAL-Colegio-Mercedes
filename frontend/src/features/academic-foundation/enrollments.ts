import {exact,decimalId,text,date,enrollmentState} from './contracts.js';
import {directoryPage} from './directory.js';
export function studentRow(value:unknown){const row=exact(value,['id','name']);return {id:decimalId(row.id),name:text(row.name)};}
export function enrollmentRow(value:unknown){
  const row=exact(value,['id','student_id','student_name','academic_period_id','period_name','grade_id','grade_name','section_id','section_name','state','effective_from','effective_until']);
  return {id:decimalId(row.id),student_id:decimalId(row.student_id),student_name:text(row.student_name),academic_period_id:decimalId(row.academic_period_id),period_name:text(row.period_name),grade_id:decimalId(row.grade_id),grade_name:text(row.grade_name),section_id:decimalId(row.section_id),section_name:text(row.section_name),state:enrollmentState(row.state),effective_from:date(row.effective_from),effective_until:row.effective_until===null?null:date(row.effective_until)};
}
export const studentPage=(value:unknown)=>directoryPage(value,studentRow);
export const enrollmentPage=(value:unknown)=>directoryPage(value,enrollmentRow);
