import {useState} from 'react';
import {MaterialMaintenancePanel} from './MaterialMaintenancePanel.js';
import type {MaterialMaintenanceClient} from './material-maintenance.js';
export function MaterialOptions({id,client,onChanged,onExpired}:{id:string;client:MaterialMaintenanceClient;onChanged:()=>Promise<void>;onExpired:()=>void}){
 const [open,setOpen]=useState(false);
 return <details className="eval-material-options" onToggle={event=>setOpen(event.currentTarget.open)}><summary>Opciones del material</summary>{open&&<MaterialMaintenancePanel id={id} client={client} onChanged={onChanged} onExpired={onExpired}/>}</details>;
}
