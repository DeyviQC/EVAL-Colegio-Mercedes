import { exact, text, decimalId, date, identityResult, period, enrollment, assignment, activityReference, historicalSubmission, confirmedTransition, relationshipInput, type EnrollmentInput, type AssignmentInput } from './contracts.js';
export class AcademicApiError extends Error {
  readonly automaticRetry=false;
  constructor(readonly category:string,readonly status:number|null,readonly correlationId:string|null=null,readonly outcomeUnknown=false) { super(category); }
}
/** Same-origin local session transport. No role/actor, caching, optimistic mutation or automatic replay. */
export function createAcademicClient(transport:typeof fetch,csrf:()=>string) {
  async function request<T>(path:string,decode:(value:unknown)=>T,body?:unknown):Promise<T> {
    const unsafe=body!==undefined; const headers:Record<string,string>={Accept:'application/json'};
    if(unsafe) { headers['Content-Type']='application/json'; headers['X-CSRF-TOKEN']=text(csrf()); }
    let response:Response;
    try { response=await transport('/academic/'+path,{method:unsafe?'POST':'GET',credentials:'same-origin',cache:'no-store',redirect:'error',headers,...(unsafe?{body:JSON.stringify(body)}:{})}); }
    catch { throw new AcademicApiError('transport_unavailable',null,null,unsafe); }
    let wire:unknown;
    try { wire=await response.json(); } catch { throw new AcademicApiError('invalid_response',response.status,null,unsafe); }
    if(!response.ok) {
      try {
        const failure=exact(wire,['error'],response.status===503?['correlation_id','automatic_retry']:[]);
        const category=text(failure.error); if(!/^[a-z_]+$/.test(category)) throw new Error();
        const correlation=failure.correlation_id===null||failure.correlation_id===undefined?null:text(failure.correlation_id);
        if(response.status===503&&failure.automatic_retry!==false) throw new Error();
        if(correlation!==null&&!/^[a-zA-Z0-9_-]{1,128}$/.test(correlation)) throw new Error();
        throw new AcademicApiError(category,response.status,correlation,['commit_outcome_unknown','rollback_outcome_unknown'].includes(category));
      } catch(error) { if(error instanceof AcademicApiError) throw error; throw new AcademicApiError('invalid_response',response.status,null,unsafe); }
    }
    try { return decode(exact(wire,['data']).data); } catch { throw new AcademicApiError('invalid_response',response.status,null,unsafe); }
  }
  const locator=decimalId;
  return {
    period:(id:string)=>request('periods/'+locator(id),period),
    enrollment:(id:string)=>request('enrollments/'+locator(id),enrollment),
    assignment:(id:string)=>request('assignments/'+locator(id),assignment),
    activity:(id:string)=>request('activities/'+locator(id),activityReference),
    submissionHistory:(id:string)=>request('submissions/'+locator(id),historicalSubmission),
    createEnrollment:(input:EnrollmentInput)=>request('enrollments',identityResult,relationshipInput(input,'enrollment')),
    planAssignment:(input:AssignmentInput)=>request('assignments',identityResult,relationshipInput(input,'assignment')),
    transfer:(id:string,destination:{grade_id:string;section_id:string})=>{
      const row=exact(destination,['grade_id','section_id']);
      return request('enrollments/'+locator(id)+'/transfer',confirmedTransition,{grade_id:locator(row.grade_id),section_id:locator(row.section_id)});
    },
    activateAssignment:(id:string)=>request('assignments/'+locator(id)+'/activate',identityResult,{}),
    closeEnrollment:(id:string,effectiveUntil:string)=>request('enrollments/'+locator(id)+'/close',identityResult,{effective_until:date(effectiveUntil)}),
    closeAssignment:(id:string,effectiveUntil:string)=>request('assignments/'+locator(id)+'/close',identityResult,{effective_until:date(effectiveUntil)}),
    /** Reserved U9 endpoint: does not currently persist a submission; propagate denial/unavailability. */
    submissionAdmission:(input:{activity_id:string})=>{ const row=exact(input,['activity_id']); return request('submissions',identityResult,{activity_id:locator(row.activity_id)}); }
  };
}
export type AcademicClient = ReturnType<typeof createAcademicClient>;