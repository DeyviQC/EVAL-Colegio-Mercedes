(function (root) {
  'use strict';
  // These are synthetic display projections, not an authorization service.
  function project(data, personaId, scenarioId) {
    const find = (rows, id) => rows.find(row => row.id === id);
    const persona = find(data.people, personaId);
    const scenario = find(data.scenarios, scenarioId);
    const period = scenario && find(data.periods, scenario.periodId);
    const empty = {
      persona: null, period: null, periodState: null,
      current: [], assignments: [], enrollments: [], history: [],
      newWorkNotice: 'No sample scope. This prototype is always read-only.'
    };
    if (!persona || !scenario || !period || !['student', 'teacher', 'director', 'vice'].includes(persona.role)) return empty;

    const enrollmentView = row => ({ ...row, period: find(data.periods, row.periodId).name });
    const assignmentView = row => {
      const teacher = find(data.people, row.teacherId);
      const parent = find(data.periods, row.periodId);
      if (!teacher || teacher.role !== 'teacher' || !parent) return null;
      return { ...row, teacher: teacher.name, period: parent.name };
    };
    const ownEnrollments = data.enrollments.filter(row =>
      (row.studentId === persona.id || persona.role === 'director') && find(data.periods, row.periodId));
    const currentEnrollments = ownEnrollments.filter(row =>
      row.periodId === period.id && row.state === 'active');
    const assignments = data.assignments.filter(row => {
      if (persona.role === 'director') return true;
      if (persona.role === 'vice') return row.id === persona.targetAssignmentId;
      if (persona.role === 'teacher') return row.teacherId === persona.id;
      return row.state === 'active' && currentEnrollments.some(enrollment =>
        row.periodId === enrollment.periodId && row.grade === enrollment.grade && row.section === enrollment.section);
    }).map(assignmentView).filter(Boolean);

    const history = [];
    for (const submission of data.submissions) {
      const activity = find(data.activities, submission.activityId);
      const assignment = find(data.assignments, submission.assignmentId);
      const enrollment = find(data.enrollments, submission.enrollmentId);
      if (!activity || !assignment || !enrollment || activity.assignmentId !== assignment.id) continue;
      if (enrollment.studentId !== submission.studentId || enrollment.periodId !== assignment.periodId ||
          enrollment.grade !== assignment.grade || enrollment.section !== assignment.section) continue;
      const student = find(data.people, submission.studentId);
      const originalAssignment = assignmentView(assignment);
      if (!student || student.role !== 'student' || !originalAssignment) continue;
      const owned = persona.role === 'director' || (persona.role === 'student'
        ? submission.studentId === persona.id
        : persona.role === 'teacher' && assignment.teacherId === persona.id);
      if (!owned) continue;
      history.push({
        ...submission, student: student.name, period: originalAssignment.period,
        assignment: originalAssignment, enrollment: enrollmentView(enrollment)
      });
    }

    return {
      persona: { ...persona }, period: period.name, periodState: scenario.periodState,
      current: persona.role === 'student'
        ? currentEnrollments.map(enrollmentView)
        : assignments.filter(row => row.state === 'active' && row.periodId === period.id),
      assignments, enrollments: ownEnrollments.map(enrollmentView), history,
      newWorkNotice: persona.role === 'vice'
        ? 'Assignment-target support only. Valid residual assignment closure and retained assignment history remain permitted. No enrollment, catalog or period management; no materials, observations, activities or submission processing. Closed parents deny new assignments, activation and replacement. This sample performs no writes.'
        : persona.role === 'director'
        ? 'Director/Admin foundation review. Valid residual enrollment and assignment closure remains permitted; closed parents deny new work, retain history and do not cascade child state. This sample performs no writes.'
        : scenario.periodState === 'closed'
        ? 'No new academic work: the sample parent period is closed.'
        : 'Read-only sample: no academic operations are implemented.'
    };
  }

  const api = { project };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.EVALProjections = api;
})(globalThis);
