import { createRoot } from 'react-dom/client';
import { SessionShell } from './features/academic-foundation/SessionShell.js';
import './styles.css';
const root=document.getElementById('root');
if(!root) throw new Error('Missing EVAL root');
createRoot(root).render(<SessionShell />);
