import {renderToStaticMarkup} from 'react-dom/server';
import {RecordedTime,recordedInstant,formatRecordedTime} from './RecordedTime.js';
const assert=(value:unknown)=>{if(!value)throw Error('Recorded time assertion failed');};
export const recordedTimeCases:readonly (readonly [string,()=>void])[]=[
 ['UTC event near midnight displays the previous calendar day in Peru',()=>{const result=formatRecordedTime('2026-10-08 00:30:00');assert(/7 oct\.? 2026/.test(result)&&result.includes('7:30')&&result.includes('hora de Perú'));}],
 ['recorded time retains exact UTC microseconds in accessible machine metadata',()=>{const value='2026-10-08 22:25:57.710156';assert(recordedInstant(value)==='2026-10-08T22:25:57.710156Z');const html=renderToStaticMarkup(<RecordedTime value={value}/>);assert(html.includes('dateTime="2026-10-08T22:25:57.710156Z"')&&html.includes('5:25')&&html.includes('hora de Perú'));}],
 ['invalid calendar events never crash a workspace or display untrusted raw text',()=>{for(const value of ['2026-02-30 10:00:00','2026-10-08 24:00:00','2026-10-08 12:60:00','2026-10-08 12:00:00.1234567','<script>private</script>']){assert(recordedInstant(value)===null);const html=renderToStaticMarkup(<RecordedTime value={value}/>);assert(html.includes('Fecha no disponible')&&!html.includes('private')&&!html.includes('dateTime'));}}],
 ['academic date-only inputs remain separate from UTC event display',()=>{assert(recordedInstant('2026-10-08')===null);assert(recordedInstant('2024-02-29 12:00:00')==='2024-02-29T12:00:00Z');assert(recordedInstant('2026-02-29 12:00:00')===null);}],
];
