import { decimalId, operationKey, historicalSubmission, enrollment, assignment, period, date, relationshipInput } from './contracts.js';
import { createAcademicClient, AcademicApiError } from './api.js';
type Case = readonly [string, () => void | Promise<void>];
function canonical(value:unknown):unknown {
  if(Array.isArray(value)) return value.map(canonical);
  if(typeof value==='object'&&value!==null) return Object.fromEntries(Object.entries(value).sort(([a],[b])=>a.localeCompare(b)).map(([key,item])=>[key,canonical(item)]));
  return value;
}
function equal(a: unknown,b: unknown): void { if(JSON.stringify(canonical(a))!==JSON.stringify(canonical(b))) throw new Error('Values differ'); }
function rejects(fn: () => unknown): void { try { fn(); } catch { return; } throw new Error('Expected rejection'); }
async function apiFailure(fn:()=>Promise<unknown>,category:string,status:number|null,unknown=false,correlation:string|null=null) {
  try { await fn(); } catch(error) { if(!(error instanceof AcademicApiError)) throw error;
    equal([error.category,error.status,error.outcomeUnknown,error.correlationId,error.automaticRetry],[category,status,unknown,correlation,false]);return; }
  throw new Error('Expected API failure');
}
const label=(id:string,displayName='Current visible label')=>({id,displayName});
const interval={effectiveFrom:'2026-01-01',effectiveUntil:null,operationalStartKey:'9007199254740993',operationalEndKeyExclusive:null};
function history() { return {submission:{id:'18446744073709551615',acceptedAt:'2026-10-07 14:20:30.123456'},activityReference:{id:'2'},
  acceptedUnderEnrollment:{id:'3',academicPeriod:label('4'),grade:label('5'),section:label('6'),...interval},
  originalTeachingAssignment:{id:'7',academicPeriod:label('4'),grade:label('5'),section:label('6'),teacher:label('8','  ÁLEX  '),instructionalEntry:{...label('9'),kind:'subject'},...interval}}; }
const enrollmentRow={id:'1',student_id:'2',academic_period_id:'3',grade_id:'4',section_id:'5',state:'active',effective_from:'2025-01-01',effective_until:null,operational_start_key:'9007199254740993',operational_end_key:null};
const assignmentRow={id:'1',teacher_id:'2',academic_period_id:'3',instructional_entry_id:'6',grade_id:'4',section_id:'5',state:'planned',effective_from:'2026-01-01',effective_until:null,operational_start_key:null,operational_end_key:null,replaces_assignment_id:null};
const input={student_id:'2',academic_period_id:'3',grade_id:'4',section_id:'5',effective_from:'2025-01-01'};
const confirmation={prior_id:'1',successor_id:'9007199254740993',operation_key:'9223372036854775807',effective_on:'2026-10-07'};
function harness(wire:unknown,status=200) {
  const calls:{path:string;init:RequestInit}[]=[];
  const transport=(async(path:RequestInfo|URL,init?:RequestInit)=>{ calls.push({path:String(path),init:init??{}}); return new Response(JSON.stringify(wire),{status,headers:{'Content-Type':'application/json'}}); }) as typeof fetch;
  return {calls,client:createAcademicClient(transport,()=> 'csrf-token')};
}
export const cases: Case[] = [
  ['unsigned BIGINT stays a decimal string',()=>equal(decimalId('18446744073709551615'),'18446744073709551615')],
  ['numeric and noncanonical identities rejected',()=>{for(const value of [1,9007199254740992,'01','0','-1','1e3','18446744073709551616',null])rejects(()=>decimalId(value));}],
  ['signed positive ordinal boundary',()=>{equal(operationKey('9223372036854775807'),'9223372036854775807');rejects(()=>operationKey('9223372036854775808'));}],
  ['dates are real calendar dates without timezone conversion',()=>{equal(date('2024-02-29'),'2024-02-29');for(const value of ['2025-02-29','2026-02-30','2026-01-01T00:00:00Z','0000-01-01'])rejects(()=>date(value));}],
  ['complete history preserves original IDs, intervals and visible labels',()=>{const source=history();equal(historicalSubmission(source),source);equal(source,history());}],
  ['history requires every retained context',()=>rejects(()=>historicalSubmission({submission:{id:'1',acceptedAt:'today'}}))],
  ['history rejects payload, grades and equality keys at every projection depth',()=>{
    rejects(()=>historicalSubmission({...history(),grades:['AD']})); rejects(()=>historicalSubmission({...history(),submission:{...history().submission,payload:'content'}}));
    const value=history();rejects(()=>historicalSubmission({...value,originalTeachingAssignment:{...value.originalTeachingAssignment,teacher:{...value.originalTeachingAssignment.teacher,name_key:'alex'}}}));
  }],
  ['history mismatched enrollment and original assignment is rejected',()=>{const value=history();value.acceptedUnderEnrollment.grade.id='99';rejects(()=>historicalSubmission(value));}],
  ['unsafe numeric ID in retained nested history is rejected',()=>{const value=history();rejects(()=>historicalSubmission({...value,activityReference:{id:2}}));}],
  ['closed/transferred enrollment states retained without authority inference',()=>{for(const state of ['active','transferred','closed'])equal(enrollment({...enrollmentRow,state}).state,state);rejects(()=>enrollment({...enrollmentRow,state:'planned'}));}],
  ['planned assignment has nullable operational boundaries',()=>{equal(assignment(assignmentRow).operational_start_key,null);for(const state of ['planned','active','closed'])equal(assignment({...assignmentRow,state}).state,state);rejects(()=>assignment({...assignmentRow,state:'replaced'}));}],
  ['period states retain active children without cascades',()=>{equal(period({id:'3',name:'  Año Á  ',start_on:'2026-01-01',end_on:'2026-12-31',state:'closed'}).name,'  Año Á  ');equal(enrollment(enrollmentRow).state,'active');}],
  ['enrollment period mandatory and no client containment rule',()=>{equal(relationshipInput(input,'enrollment'),input);rejects(()=>relationshipInput({...input,academic_period_id:undefined},'enrollment'));equal(relationshipInput({...input,effective_until:null},'enrollment').effective_until,null);}],
  ['read transport stays relative, uncached, session-bound and no authority headers',async()=>{const {client,calls}=harness({data:enrollmentRow});equal(await client.enrollment('1'),enrollmentRow);equal(calls[0]?.path,'/academic/enrollments/1');equal(calls[0]?.init,{method:'GET',credentials:'same-origin',cache:'no-store',redirect:'error',headers:{Accept:'application/json'}});}],
  ['locator injection fails before transport',()=>{const {client,calls}=harness({});rejects(()=>client.enrollment('1?role=director'));equal(calls.length,0);}],
  ['transfer confirms only successful server result and preserves ordinal string',async()=>{const {client,calls}=harness({data:confirmation});equal(await client.transfer('1',{grade_id:'4',section_id:'5'}),confirmation);equal(JSON.parse(String(calls[0]?.init.body)),{grade_id:'4',section_id:'5'});equal((calls[0]?.init.headers as Record<string,string>)['X-CSRF-TOKEN'],'csrf-token');}],
  ['scheduled retroactive corrective and client-authority transfer fields rejected',()=>{for(const field of ['effective_on','scheduled_on','correction','actor','role','student_id','academic_period_id','operation_key']){const {client,calls}=harness({});rejects(()=>client.transfer('1',{grade_id:'4',section_id:'5',[field]:'1'}));equal(calls.length,0);}}],
  ['no enrollment containment/open-end expiry introduced',async()=>{const {client,calls}=harness({data:{id:'1'}},201);equal(await client.createEnrollment({...input,effective_until:null}),{id:'1'});equal(JSON.parse(String(calls[0]?.init.body)),{...input,effective_until:null});}],
  ['client enrollment role/scope authority fields rejected',()=>{const {client,calls}=harness({});rejects(()=>client.createEnrollment({...input,...{role:'director'}}));equal(calls.length,0);}],
  ['planned-parent assignment sends planning input without activation',async()=>{const {client,calls}=harness({data:{id:'1'}},201);await client.planAssignment({teacher_id:'2',academic_period_id:'3',instructional_entry_id:'6',grade_id:'4',section_id:'5',effective_from:'2026-01-01'});equal(calls[0]?.path,'/academic/assignments');equal(calls.length,1);}],
  ['submission input rejects recipient, scope, alternate assignment and payload',()=>{for(const field of ['recipient','teacher_id','student_id','grade_id','section_id','accepted_under_enrollment_id','assignment_id','payload','late']){const {client,calls}=harness({});rejects(()=>client.submissionAdmission({activity_id:'2',[field]:'1'}));equal(calls.length,0);}}],
  ['reserved submission admission does not imply persistence',async()=>{const {client,calls}=harness({error:'not_found'},404);await apiFailure(()=>client.submissionAdmission({activity_id:'2'}),'not_found',404);equal(JSON.parse(String(calls[0]?.init.body)),{activity_id:'2'});equal(calls.length,1);}],
  ['closed parent new-work denial propagates without changing current scope',async()=>{const {client,calls}=harness({error:'period_not_active'},409);await apiFailure(()=>client.createEnrollment(input),'period_not_active',409);equal(calls.length,1);}],
  ['closed parent does not prevent client from requesting valid cleanup/history',async()=>{const cleanup=harness({data:{id:'1'}});await cleanup.client.closeAssignment('1','2026-10-07');equal(cleanup.calls[0]?.path,'/academic/assignments/1/close');const retained=harness({data:history()});equal(await retained.client.submissionHistory('1'),history());}],
  ['empty CSRF fails before write transport',async()=>{let calls=0;const client=createAcademicClient((async()=>{calls++;return new Response();}) as typeof fetch,()=> '');let denied=false;try{await client.createEnrollment(input);}catch{denied=true;}equal(denied,true);equal(calls,0);}],
  ['wire success shape is fail closed and unsafe malformed acknowledgement uncertain',async()=>{const {client,calls}=harness({data:{...confirmation,role:'director'}});await apiFailure(()=>client.transfer('1',{grade_id:'4',section_id:'5'}),'invalid_response',200,true);equal(calls.length,1);}],
  ['actual labels remain unchanged by client equality or presentation normalization',async()=>{const {client}=harness({data:history()});equal((await client.submissionHistory('1')).originalTeachingAssignment.teacher.displayName,'  ÁLEX  ');}],
  ['network loss during mutation is uncertain and never replayed',async()=>{let calls=0;const client=createAcademicClient((async()=>{calls++;throw new Error('lost acknowledgement');}) as typeof fetch,()=> 'token');await apiFailure(()=>client.transfer('1',{grade_id:'4',section_id:'5'}),'transport_unavailable',null,true);equal(calls,1);}],
  ['unknown commit correlation preserved without automatic retry',async()=>{const {client,calls}=harness({error:'commit_outcome_unknown',correlation_id:'abc123',automatic_retry:false},503);await apiFailure(()=>client.transfer('1',{grade_id:'4',section_id:'5'}),'commit_outcome_unknown',503,true,'abc123');equal(calls.length,1);}],
  ['untrusted retry directive and internal error leakage rejected',async()=>{const {client}=harness({error:'commit_outcome_unknown',automatic_retry:true,correlation_id:'abc123'},503);await apiFailure(()=>client.closeEnrollment('1','2026-10-07'),'invalid_response',503,true);const leaked=harness({error:'database_unavailable',sql:'SELECT secret'},503);await apiFailure(()=>leaked.client.enrollment('1'),'invalid_response',503);}],
  ...([401,403,404,419,422,429] as const).map(status=>[`${status} denial preserved without replay`,async()=>{const {client,calls}=harness({error:'denied'},status);await apiFailure(()=>client.enrollment('1'),'denied',status);equal(calls.length,1);}] as Case)
];
// Compile-only negative contracts: no scheduling, numeric IDs or recipient selectors.
function typeEvidence(client:ReturnType<typeof createAcademicClient>) {
  // @ts-expect-error numeric identity is forbidden
  client.enrollment(1);
  // @ts-expect-error transfer cannot schedule
  client.transfer('1',{grade_id:'4',section_id:'5',effective_on:'2026-10-07'});
  // @ts-expect-error student recipient cannot be selected
  client.submissionAdmission({activity_id:'1',teacher_id:'2'});
}
void typeEvidence;