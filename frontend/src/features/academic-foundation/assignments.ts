import {exact,decimalId,text,date,assignmentState,periodState} from './contracts.js';
import {directoryPage} from './directory.js';
export function assignmentRow(value:unknown){
  const row=exact(value,['id','teacher_id','teacher_name','academic_period_id','period_name','period_state','instructional_entry_id','entry_name','grade_id','grade_name','section_id','section_name','state','effective_from','effective_until','replaces_assignment_id']);
  return {id:decimalId(row.id),teacher_id:decimalId(row.teacher_id),teacher_name:text(row.teacher_name),academic_period_id:decimalId(row.academic_period_id),period_name:text(row.period_name),period_state:periodState(row.period_state),instructional_entry_id:decimalId(row.instructional_entry_id),entry_name:text(row.entry_name),grade_id:decimalId(row.grade_id),grade_name:text(row.grade_name),section_id:decimalId(row.section_id),section_name:text(row.section_name),state:assignmentState(row.state),effective_from:date(row.effective_from),effective_until:row.effective_until===null?null:date(row.effective_until),replaces_assignment_id:row.replaces_assignment_id===null?null:decimalId(row.replaces_assignment_id)};
}
export const assignmentPage=(value:unknown)=>directoryPage(value,assignmentRow);
export type AssignmentKind='assignments'|'teachers'|'periods'|'entries'|'grades'|'sections';
