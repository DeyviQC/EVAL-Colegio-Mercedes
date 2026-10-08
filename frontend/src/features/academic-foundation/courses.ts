import { exact, text, decimalId, assignmentState, periodState } from './contracts.js';
import { directoryPage } from './directory.js';
export function course(value:unknown){const row=exact(value,['id','subject','kind','grade','section','period','period_state','state','teacher','can_publish']);
  if(!['subject','area'].includes(row.kind as string)||typeof row.can_publish!=='boolean')throw new Error('Invalid course');
  const result={id:decimalId(row.id),subject:text(row.subject),kind:row.kind as 'subject'|'area',grade:text(row.grade),section:text(row.section),period:text(row.period),period_state:periodState(row.period_state),state:assignmentState(row.state),teacher:text(row.teacher),can_publish:row.can_publish};
  if(result.can_publish&&(result.state!=='active'||result.period_state!=='active'))throw new Error('Invalid affordance');return result;
}
export type Course=ReturnType<typeof course>;
export const coursesPage=(value:unknown)=>directoryPage(value,course);
