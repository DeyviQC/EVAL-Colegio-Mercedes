/** Wire validation is not academic authorization. The server owns all authority. */
export class ContractError extends Error { constructor() { super('Invalid EVAL academic contract'); } }
type Decoder<T> = (value: unknown) => T;
export function record(value: unknown): Record<string, unknown> {
  if(typeof value !== 'object' || value === null || Array.isArray(value)) throw new ContractError();
  return value as Record<string, unknown>;
}
export function exact(value: unknown, fields: readonly string[], optional: readonly string[] = []): Record<string, unknown> {
  const row=record(value);
  if(Object.keys(row).some(key=>!fields.includes(key)&&!optional.includes(key)) || fields.some(key=>!Object.hasOwn(row,key))) throw new ContractError();
  return row;
}
export function text(value: unknown): string { if(typeof value!=='string'||!value.length) throw new ContractError(); return value; }
export function decimalId(value: unknown): string {
  if(typeof value!=='string'||!/^[1-9][0-9]*$/.test(value)||value.length>20||(value.length===20&&value>'18446744073709551615')) throw new ContractError();
  return value;
}
export function operationKey(value: unknown): string {
  const key=decimalId(value); if(key.length>19||(key.length===19&&key>'9223372036854775807')) throw new ContractError(); return key;
}
export function date(value: unknown): string {
  const input=text(value); if(!/^\d{4}-\d{2}-\d{2}$/.test(input)||input<'0001-01-01') throw new ContractError();
  const parsed=new Date(input+'T00:00:00Z'); if(!Number.isFinite(parsed.getTime())||parsed.toISOString().slice(0,10)!==input) throw new ContractError(); return input;
}
function nullable<T>(value: unknown,decode: Decoder<T>): T|null { return value===null?null:decode(value); }
function choice<T extends string>(value: unknown,choices: readonly T[]): T { if(!choices.includes(value as T)) throw new ContractError(); return value as T; }
export const periodState=(value: unknown)=>choice(value,['planned','active','closed'] as const);
export const enrollmentState=(value: unknown)=>choice(value,['active','transferred','closed'] as const);
export const assignmentState=(value: unknown)=>choice(value,['planned','active','closed'] as const);
function label(value: unknown) { const row=exact(value,['id','displayName']); return {id:decimalId(row.id),displayName:text(row.displayName)}; }
function interval(row: Record<string,unknown>) {
  return {effectiveFrom:date(row.effectiveFrom),effectiveUntil:nullable(row.effectiveUntil,date),
    operationalStartKey:nullable(row.operationalStartKey,operationKey),operationalEndKeyExclusive:nullable(row.operationalEndKeyExclusive,operationKey)};
}
const intervalFields=['effectiveFrom','effectiveUntil','operationalStartKey','operationalEndKeyExclusive'];
export function historicalSubmission(value: unknown) {
  const row=exact(value,['submission','activityReference','acceptedUnderEnrollment','originalTeachingAssignment']);
  const submission=exact(row.submission,['id','acceptedAt']); const acceptedAt=text(submission.acceptedAt);
  if(!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/.test(acceptedAt)) throw new ContractError();
  const activity=exact(row.activityReference,['id']);
  const enrollment=exact(row.acceptedUnderEnrollment,['id','academicPeriod','grade','section',...intervalFields]);
  const assignment=exact(row.originalTeachingAssignment,['id','academicPeriod','teacher','instructionalEntry','grade','section',...intervalFields]);
  const entry=exact(assignment.instructionalEntry,['id','displayName','kind']);
  const acceptedUnderEnrollment={id:decimalId(enrollment.id),academicPeriod:label(enrollment.academicPeriod),grade:label(enrollment.grade),section:label(enrollment.section),...interval(enrollment)};
  const originalTeachingAssignment={id:decimalId(assignment.id),academicPeriod:label(assignment.academicPeriod),teacher:label(assignment.teacher),
    instructionalEntry:{...label({id:entry.id,displayName:entry.displayName}),kind:choice(entry.kind,['subject','area'] as const)},grade:label(assignment.grade),section:label(assignment.section),...interval(assignment)};
  for(const field of ['academicPeriod','grade','section'] as const) if(acceptedUnderEnrollment[field].id!==originalTeachingAssignment[field].id) throw new ContractError();
  return {submission:{id:decimalId(submission.id),acceptedAt},activityReference:{id:decimalId(activity.id)},acceptedUnderEnrollment,originalTeachingAssignment};
}
export type HistoricalSubmission = ReturnType<typeof historicalSubmission>;
export function period(value: unknown) {
  const row=exact(value,['id','name','start_on','end_on','state']);
  return {id:decimalId(row.id),name:text(row.name),start_on:date(row.start_on),end_on:date(row.end_on),state:periodState(row.state)};
}
function relationship(value: unknown,extra: readonly string[]) {
  return exact(value,['id','academic_period_id','grade_id','section_id','state','effective_from','effective_until','operational_start_key','operational_end_key',...extra]);
}
function scope(row: Record<string,unknown>) {
  return {id:decimalId(row.id),academic_period_id:decimalId(row.academic_period_id),grade_id:decimalId(row.grade_id),section_id:decimalId(row.section_id),
    effective_from:date(row.effective_from),effective_until:nullable(row.effective_until,date),operational_start_key:nullable(row.operational_start_key,operationKey),operational_end_key:nullable(row.operational_end_key,operationKey)};
}
export function enrollment(value: unknown) { const row=relationship(value,['student_id']); return {...scope(row),student_id:decimalId(row.student_id),state:enrollmentState(row.state)}; }
export function assignment(value: unknown) {
  const row=relationship(value,['teacher_id','instructional_entry_id','replaces_assignment_id']);
  return {...scope(row),teacher_id:decimalId(row.teacher_id),instructional_entry_id:decimalId(row.instructional_entry_id),replaces_assignment_id:nullable(row.replaces_assignment_id,decimalId),state:assignmentState(row.state)};
}
export function identityResult(value: unknown) { const row=exact(value,['id']); return {id:decimalId(row.id)}; }
export function confirmedTransition(value: unknown) {
  const row=exact(value,['prior_id','successor_id','operation_key','effective_on']);
  return {prior_id:decimalId(row.prior_id),successor_id:decimalId(row.successor_id),operation_key:operationKey(row.operation_key),effective_on:date(row.effective_on)};
}
export function activityReference(value: unknown) { const row=exact(value,['id','teaching_assignment_id']); return {id:decimalId(row.id),teaching_assignment_id:decimalId(row.teaching_assignment_id)}; }
export type EnrollmentInput={student_id:string;academic_period_id:string;grade_id:string;section_id:string;effective_from:string;effective_until?:string|null};
export type AssignmentInput={teacher_id:string;academic_period_id:string;instructional_entry_id:string;grade_id:string;section_id:string;effective_from:string;effective_until?:string|null};
export function relationshipInput(value: unknown,kind:'enrollment'|'assignment') {
  const ids=kind==='enrollment'?['student_id','academic_period_id','grade_id','section_id']:['teacher_id','academic_period_id','instructional_entry_id','grade_id','section_id'];
  const row=exact(value,[...ids,'effective_from'],['effective_until']); const result:Record<string,string|null>={};
  for(const field of ids) result[field]=decimalId(row[field]); result.effective_from=date(row.effective_from);
  if(Object.hasOwn(row,'effective_until')) result.effective_until=nullable(row.effective_until,date);
  // No enrollment-period containment, expiry or authority inferred from client dates.
  return result;
}