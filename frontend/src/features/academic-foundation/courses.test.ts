import { course, coursesPage } from './courses.js';
import { createAcademicClient } from './api.js';
const row={id:'9007199254740993',subject:'Matemática',kind:'subject',grade:'2.º',section:'B',period:'2026',period_state:'active',state:'active',teacher:'Synthetic teacher',can_publish:true};
function assert(value:unknown){if(!value)throw new Error('Course assertion failed');}
function rejects(fn:()=>unknown){try{fn();}catch{return;}throw new Error('Expected rejection');}
export const courseCases:[string,()=>void|Promise<void>][]=[
 ['course uses retained labels and opaque string identity',()=>{const view=course(row);assert(view.id===row.id&&view.subject==='Matemática'&&view.teacher===row.teacher);}],
 ['course rejects leaked authority and invalid publication affordance',()=>{rejects(()=>course({...row,actor:'1'}));rejects(()=>course({...row,id:1}));rejects(()=>course({...row,state:'planned'}));}],
 ['course collection accepts explicit empty state',()=>assert(coursesPage({items:[],next_after:null}).items.length===0)],
 ['actual courses use local authoritative read endpoints',async()=>{const calls:string[]=[];const client=createAcademicClient((async(input,options)=>{calls.push(String(input));assert(options?.credentials==='same-origin'&&options?.method==='GET');return new Response(JSON.stringify({data:calls.length===1?{items:[row],next_after:null}:row}));}) as typeof fetch,()=> 'csrf');await client.myCourses(coursesPage);await client.course(row.id,course);assert(calls[0]==='/academic/my-courses'&&calls[1]==='/academic/my-courses/'+row.id);}],
];
