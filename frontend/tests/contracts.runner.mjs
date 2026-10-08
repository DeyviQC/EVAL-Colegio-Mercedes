import test from 'node:test';
import { cases } from '../.test-build/features/academic-foundation/contracts.test.js';
import { uiCases } from '../.test-build/features/academic-foundation/ui.test.js';
import { navigationCases } from '../.test-build/features/academic-foundation/navigation.test.js';
import { directoryCases } from '../.test-build/features/academic-foundation/directory.test.js';
import { courseCases } from '../.test-build/features/academic-foundation/courses.test.js';
import { materialCases } from '../.test-build/features/academic-foundation/materials.test.js';
for (const [name, run] of [...cases,...uiCases,...navigationCases,...directoryCases,...courseCases,...materialCases]) test(name, run);
