import test from 'node:test';
import { cases } from '../.test-build/features/academic-foundation/contracts.test.js';
for (const [name, run] of cases) test(name, run);
