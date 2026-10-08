import test from 'node:test';
import { cases } from '../.test-build/features/academic-foundation/contracts.test.js';
import { uiCases } from '../.test-build/features/academic-foundation/ui.test.js';
import { navigationCases } from '../.test-build/features/academic-foundation/navigation.test.js';
for (const [name, run] of [...cases,...uiCases,...navigationCases]) test(name, run);
