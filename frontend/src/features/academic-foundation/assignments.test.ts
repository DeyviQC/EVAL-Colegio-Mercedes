import {createElement} from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import {assignmentPage} from './assignments.js';
import {createAcademicClient,AcademicApiError} from './api.js';
import {AssignmentWorkspace} from './AssignmentWorkspace.js';
const assert=(value:unknown)=>{if(!value)throw new Error('Assignment assertion failed');};
const sample={id:'9007199254740993',teacher_id:'2',teacher_name:'Docente',academic_period_id:'3',period_name:'2026',period_state:'active',instructional_entry_id:'4',entry_name:'Área',grade_id:'5',grade_name:'2.º',section_id:'6',section_name:'B',state:'closed',effective_from:'2026-01-01',effective_until:'2026-10-08',replaces_assignment_id:'1'};
function rejects(run:()=>unknown){try{run();}catch{return;}throw new Error('Expected rejection');}
export const assignmentCases:[string,()=>void|Promise<void>][]=[
 ['assignment collection retains named original contexts and replacement lineage',()=>{const row=assignmentPage({items:[sample],next_after:null}).items[0]!;assert(row.id===sample.id&&row.replaces_assignment_id==='1'&&row.state==='closed');}],
 ['assignment collection rejects private fields malformed IDs and states',()=>{for(const row of [{...sample,id:1},{...sample,students:[]},{...sample,state:'transferred'},{...sample,period_state:'unknown'},{...sample,replaces_assignment_id:1}])rejects(()=>assignmentPage({items:[row],next_after:null}));}],
 ['assignment discovery is duty-specific and has no supplied actor authority',async()=>{let url='';const client=createAcademicClient((async(path,options)=>{url=String(path);assert(options?.credentials==='same-origin'&&options.method==='GET');return new Response(JSON.stringify({data:{items:[],next_after:null}}));})as typeof fetch,()=> 'csrf');await client.assignmentDirectory('assignments',assignmentPage,'9');assert(url==='/academic/assignment-workspace?kind=assignments&after=9');rejects(()=>client.assignmentDirectory('students' as never,assignmentPage));}],
 ['replacement sends only successor teacher and never replays lost confirmation',async()=>{let calls=0;const client=createAcademicClient((async(path,options)=>{calls++;assert(String(path)==='/academic/assignments/1/replace');assert(options?.body===JSON.stringify({teacher_id:'2'}));throw new Error('Lost acknowledgement');})as typeof fetch,()=> 'csrf');try{await client.replaceTeacher('1','2');}catch(error){assert(error instanceof AcademicApiError&&error.outcomeUnknown);}assert(calls===1);}],
 ['assignment form names academic selections and separates planning from activation',()=>{const html=renderToStaticMarkup(createElement(AssignmentWorkspace,{client:createAcademicClient(fetch,()=> 'csrf'),onExpired:()=>{}}));assert(html.includes('Planificar asignación')&&html.includes('Materia o área')&&html.includes('Selecciona un docente'));assert(!html.includes('name="role"')&&!html.includes('name="actor"')&&!html.includes('type="number"'));}],
];
