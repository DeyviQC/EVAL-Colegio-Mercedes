const formatter=new Intl.DateTimeFormat('es-PE',{dateStyle:'medium',timeStyle:'short',timeZone:'America/Lima'});
/** Database event timestamps are UTC; declared academic dates are not instants. */
export function recordedInstant(value:string):string|null {
 if(!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/.test(value))return null;
 const iso=value.replace(' ','T')+'Z',parsed=new Date(iso);
 return Number.isFinite(parsed.getTime())&&parsed.toISOString().slice(0,19)===iso.slice(0,19)?iso:null;
}
export function formatRecordedTime(value:string):string {
 const iso=recordedInstant(value);return iso===null?'Fecha no disponible':formatter.format(new Date(iso))+' (hora de Perú)';
}
export function RecordedTime({value}:{value:string}) {
 const iso=recordedInstant(value);return iso===null?<span>Fecha no disponible</span>:<time dateTime={iso}>{formatRecordedTime(value)}</time>;
}
