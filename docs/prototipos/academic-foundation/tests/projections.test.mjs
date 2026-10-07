import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const fixtures = require('../fixtures.js');
const { project } = require('../projections.js');

test('management projections distinguish director history and assignment-only vice principal support', () => {
  const director = project(fixtures, 'director-sample', 'closed');
  const vice = project(fixtures, 'vice-sample', 'closed');
  assert.equal(director.enrollments.length, fixtures.enrollments.length);
  assert.equal(director.history.length, fixtures.submissions.length);
  assert.equal(vice.assignments.length, 1);
  assert.equal(vice.assignments[0].id, 'assignment-original');
  assert.deepEqual(vice.enrollments, []);
  assert.deepEqual(vice.history, []);
  assert.match(vice.newWorkNotice, /residual assignment closure/);
  assert.match(vice.newWorkNotice, /No enrollment, catalog or period management/);
});

test('student current scope is separate from original accepted context', () => {
  const view = project(fixtures, 'student-river', 'current');
  assert.equal(view.current[0].section, 'Section B');
  assert.equal(view.history[0].enrollment.section, 'Section A');
  assert.equal(view.history[0].assignment.teacher, 'Morgan Vale');
  assert.equal(view.history[0].assignment.id, 'assignment-original');
  assert.equal(view.history[0].period, 'Sample 2026');
  assert.equal(view.history[0].activityId, 'activity-original');
});

test('student sees own history only and concurrent assignment identities survive', () => {
  const view = project(fixtures, 'student-river', 'current');
  assert.deepEqual(view.history.map(row => row.id), ['submission-river']);
  assert.deepEqual(view.assignments.map(row => row.id), ['assignment-jules-b', 'assignment-peer']);
  assert.equal(view.assignments[0].entry, view.assignments[1].entry);
});

test('teacher history belongs to original owner, never the replacement', () => {
  const original = project(fixtures, 'teacher-morgan', 'current');
  const replacement = project(fixtures, 'teacher-jules', 'current');
  assert.deepEqual(original.history.map(row => row.id), ['submission-river']);
  assert.equal(original.assignments.length, 2);
  assert.equal(original.assignments[0].state, 'closed');
  assert.equal(replacement.history.length, 0);
  assert.deepEqual(replacement.assignments.map(row => row.id), ['assignment-new', 'assignment-jules-b']);
  assert.equal(replacement.assignments[0].section, original.assignments[0].section);
});

test('closed parent preserves active children and own retained history without new-work authority', () => {
  const view = project(fixtures, 'student-river', 'closed');
  assert.equal(view.periodState, 'closed');
  assert.equal(view.current[0].state, 'active');
  assert.equal(view.assignments[0].state, 'active');
  assert.equal(view.history.length, 1);
  assert.equal(view.newWorkNotice, 'No new academic work: the sample parent period is closed.');
});

test('empty and unknown personas fail closed; unknown scenario produces no records', () => {
  for (const id of ['student-empty', 'teacher-empty', 'unknown']) {
    const view = project(fixtures, id, 'current');
    assert.deepEqual(view.current, []);
    assert.deepEqual(view.assignments, []);
    assert.deepEqual(view.history, []);
  }
  assert.equal(project(fixtures, 'student-river', 'unknown').history.length, 0);
});

test('missing and inconsistent retained references safely omit history', () => {
  for (const transform of [
    data => { data.assignments = data.assignments.filter(row => row.id !== 'assignment-original'); },
    data => { data.enrollments = data.enrollments.filter(row => row.id !== 'enrollment-old'); },
    data => { data.submissions[0].enrollmentId = 'enrollment-other'; },
    data => { data.activities[0].assignmentId = 'assignment-new'; },
    data => { data.periods = []; },
    data => { data.people = data.people.filter(row => row.id !== 'teacher-morgan'); }
  ]) {
    const data = structuredClone(fixtures);
    transform(data);
    assert.deepEqual(project(data, 'student-river', 'current').history, []);
  }
});

test('fixtures are deeply frozen and projections never mutate or expose fixture references', () => {
  assert.equal(Object.isFrozen(fixtures), true);
  assert.equal(Object.isFrozen(fixtures.enrollments[0]), true);
  assert.throws(() => { fixtures.enrollments[0].section = 'Changed'; }, TypeError);
  const before = JSON.stringify(fixtures);
  const view = project(fixtures, 'student-river', 'closed');
  view.history[0].assignment.teacher = 'Changed';
  project(fixtures, 'teacher-morgan', 'current');
  assert.equal(JSON.stringify(fixtures), before);
  assert.equal(project(fixtures, 'student-river', 'current').history[0].assignment.teacher, 'Morgan Vale');
});

test('every sample submission has internally consistent original scope', () => {
  for (const submission of fixtures.submissions) {
    const enrollment = fixtures.enrollments.find(row => row.id === submission.enrollmentId);
    const assignment = fixtures.assignments.find(row => row.id === submission.assignmentId);
    assert.equal(enrollment.studentId, submission.studentId);
    assert.equal(enrollment.periodId, assignment.periodId);
    assert.equal(enrollment.grade, assignment.grade);
    assert.equal(enrollment.section, assignment.section);
    const own = project(fixtures, submission.studentId, 'current');
    assert.equal(own.history.some(row => row.id === submission.id), true);
  }
});
