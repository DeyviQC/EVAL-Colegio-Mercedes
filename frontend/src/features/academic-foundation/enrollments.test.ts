import {createElement} from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {enrollmentPage,studentPage} from './enrollments.js';
import {createAcademicClient,AcademicApiError} from './api.js';
import {EnrollmentWorkspace} from './EnrollmentWorkspace.js';
const assert=(value:unknown)=>{if(!value)throw new Error('Enrollment assertion failed');};
const sample={id:'9007199254740993',student_id:'2',student_name:'Estudiante',academic_period_id:'3',period_name:'2026',grade_id:'4',grade_name:'2.º',section_id:'5',section_name:'B',state:'active',effective_from:'2026-01-01',effective_until:null};
function rejects(run:()=>unknown){try{run();}catch{return;}throw new Error('Expected rejection');}
export const enrollmentCases:[string,()=>void|Promise<void>][]=[
 ['enrollment directory keeps exact IDs, names and open-ended history',()=>{const row=enrollmentPage({items:[sample],next_after:null}).items[0]!;assert(row.id===sample.id&&row.effective_until===null);assert(studentPage({items:[{id:'1',name:'  María  '}],next_after:null}).items[0]!.name==='  María  ');}],
 ['enrollment directory rejects numeric IDs, unexpected private fields and malformed pages',()=>{for(const item of [{...sample,id:1},{...sample,password:'secret'},{...sample,state:'planned'},{...sample,effective_from:'2026-02-30'}])rejects(()=>enrollmentPage({items:[item],next_after:null}));rejects(()=>studentPage({items:[{id:'1',name:'Name',login:'private'}],next_after:null}));rejects(()=>enrollmentPage({items:[sample],next_after:'1'}));}],
 ['enrollment collections use session reads without client role authority',async()=>{let seen='';const client=createAcademicClient((async(url,options)=>{seen=String(url);assert(options?.credentials==='same-origin'&&options.method==='GET');return new Response(JSON.stringify({data:{items:[],next_after:null}}));})as typeof fetch,()=> 'csrf');await client.enrollmentDirectory('enrollment-students',studentPage,'9');assert(seen==='/academic/enrollment-students?after=9');rejects(()=>client.enrollmentDirectory('users' as never,studentPage));}],
 ['enrollment writes require server confirmation and do not retry uncertain acknowledgements',async()=>{let count=0;const client=createAcademicClient((async()=>{count++;throw new Error('lost response');}) as typeof fetch,()=> 'csrf');try{await client.createEnrollment({student_id:'1',academic_period_id:'2',grade_id:'3',section_id:'4',effective_from:'2026-01-01'});}catch(error){assert(error instanceof AcademicApiError&&error.outcomeUnknown);}assert(count===1);}],
 ['enrollment form uses named selections rather than raw identity or role entry',()=>{const html=renderToStaticMarkup(createElement(EnrollmentWorkspace,{client:createAcademicClient(fetch,()=> 'csrf'),onExpired:()=>{}}));assert(html.includes('Crear matrícula')&&html.includes('Selecciona un estudiante')&&html.includes('Fin (opcional)'));assert(!html.includes('name="role"')&&!html.includes('name="actor"')&&!html.includes('type="number"'));}],
];
