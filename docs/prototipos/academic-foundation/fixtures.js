(function (root) {
  'use strict';
  function freeze(value) {
    for (const child of Object.values(value)) {
      if (child && typeof child === 'object') freeze(child);
    }
    return Object.freeze(value);
  }

  const fixtures = freeze({
    people: [
      { id: 'director-sample', role: 'director', name: 'Alex Willow' },
      { id: 'vice-sample', role: 'vice', name: 'Taylor Ash', targetAssignmentId: 'assignment-original' },
      { id: 'student-river', role: 'student', name: 'River Linden' },
      { id: 'student-other', role: 'student', name: 'Sky Rowan' },
      { id: 'student-empty', role: 'student', name: 'Casey Fern (empty sample)' },
      { id: 'teacher-morgan', role: 'teacher', name: 'Morgan Vale' },
      { id: 'teacher-jules', role: 'teacher', name: 'Jules Cedar' },
      { id: 'teacher-peer', role: 'teacher', name: 'Avery Brook' },
      { id: 'teacher-empty', role: 'teacher', name: 'Robin Elm (empty sample)' }
    ],
    periods: [{ id: 'period-sample', name: 'Sample 2026', state: 'active' }],
    scenarios: [
      { id: 'current', name: 'After transfer and replacement', periodId: 'period-sample', periodState: 'active' },
      { id: 'closed', name: 'Closed parent, retained active children', periodId: 'period-sample', periodState: 'closed' }
    ],
    enrollments: [
      {
        id: 'enrollment-old', studentId: 'student-river', periodId: 'period-sample',
        grade: 'Grade 2', section: 'Section A', state: 'transferred',
        declaredFrom: '2026-03-02', declaredUntil: '2026-06-01', startKey: 'sample-K10', endKey: 'sample-K30'
      },
      {
        id: 'enrollment-now', studentId: 'student-river', periodId: 'period-sample',
        grade: 'Grade 2', section: 'Section B', state: 'active',
        declaredFrom: '2026-06-01', declaredUntil: null, startKey: 'sample-K30', endKey: null
      },
      {
        id: 'enrollment-other', studentId: 'student-other', periodId: 'period-sample',
        grade: 'Grade 2', section: 'Section B', state: 'active',
        declaredFrom: '2026-03-02', declaredUntil: null, startKey: 'sample-K11', endKey: null
      }
    ],
    assignments: [
      {
        id: 'assignment-original', teacherId: 'teacher-morgan', periodId: 'period-sample',
        entry: 'Mathematics', kind: 'subject', grade: 'Grade 2', section: 'Section A', state: 'closed',
        declaredFrom: '2026-03-02', declaredUntil: '2026-06-01', startKey: 'sample-K12', endKey: 'sample-K31'
      },
      {
        id: 'assignment-morgan-area', teacherId: 'teacher-morgan', periodId: 'period-sample',
        entry: 'Creative Inquiry', kind: 'area', grade: 'Grade 3', section: 'Section C', state: 'active',
        declaredFrom: '2026-03-02', declaredUntil: null, startKey: 'sample-K13', endKey: null
      },
      {
        id: 'assignment-new', teacherId: 'teacher-jules', periodId: 'period-sample',
        replacesAssignmentId: 'assignment-original',
        entry: 'Mathematics', kind: 'subject', grade: 'Grade 2', section: 'Section A', state: 'active',
        declaredFrom: '2026-06-01', declaredUntil: null, startKey: 'sample-K31', endKey: null
      },
      {
        id: 'assignment-jules-b', teacherId: 'teacher-jules', periodId: 'period-sample',
        entry: 'Mathematics', kind: 'subject', grade: 'Grade 2', section: 'Section B', state: 'active',
        declaredFrom: '2026-03-02', declaredUntil: null, startKey: 'sample-K15', endKey: null
      },
      {
        id: 'assignment-peer', teacherId: 'teacher-peer', periodId: 'period-sample',
        entry: 'Mathematics', kind: 'subject', grade: 'Grade 2', section: 'Section B', state: 'active',
        declaredFrom: '2026-03-02', declaredUntil: null, startKey: 'sample-K14', endKey: null
      }
    ],
    activities: [
      { id: 'activity-original', assignmentId: 'assignment-original' },
      { id: 'activity-other', assignmentId: 'assignment-peer' }
    ],
    submissions: [
      {
        id: 'submission-river', studentId: 'student-river', activityId: 'activity-original',
        assignmentId: 'assignment-original', enrollmentId: 'enrollment-old',
        acceptedAt: '2026-05-20T09:00:00Z', acceptanceKey: 'sample-K20'
      },
      {
        id: 'submission-other', studentId: 'student-other', activityId: 'activity-other',
        assignmentId: 'assignment-peer', enrollmentId: 'enrollment-other',
        acceptedAt: '2026-05-21T09:00:00Z', acceptanceKey: 'sample-K21'
      }
    ]
  });
  if (typeof module !== 'undefined' && module.exports) module.exports = fixtures;
  else root.EVALFixtures = fixtures;
})(globalThis);
