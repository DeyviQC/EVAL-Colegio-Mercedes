(function (root) {
  'use strict';
  function mount(doc, data, project) {
    const ids = ['persona', 'scenario', 'summary', 'current', 'assignments', 'enrollments', 'history', 'status'];
    const elements = Object.fromEntries(ids.map(id => [id, doc.getElementById(id)]));
    const node = (tag, text, className) => {
      const element = doc.createElement(tag);
      if (text !== undefined) element.textContent = text;
      if (className) element.className = className;
      return element;
    };
    function details(pairs) {
      const list = node('dl');
      for (const [label, value] of pairs) list.append(node('dt', label), node('dd', value));
      return list;
    }
    function card(title, pairs) {
      const article = node('article', undefined, 'card');
      article.append(node('h3', title), details(pairs));
      return article;
    }
    const interval = row => [
      ['Declared sample start date', row.declaredFrom],
      ['Declared sample end date', row.declaredUntil || 'No recorded end'],
      ['Synthetic operational start key', row.startKey],
      ['Synthetic operational end key (exclusive)', row.endKey || 'No recorded end']
    ];
    const scope = row => [
      ['Sample period', row.period], ['Grade', row.grade], ['Section', row.section],
      ['Retained child state — not work permission', row.state]
    ];
    const assignmentPairs = row => [
      ['Assignment ID', row.id], ['Teacher', row.teacher],
      ['Instructional entry', `${row.entry} (${row.kind})`], ...scope(row), ...interval(row),
      ['Replacement of assignment', row.replacesAssignmentId || 'Not a replacement']
    ];
    function panel(id, rows, emptyText, render) {
      elements[id].replaceChildren(...(rows.length
        ? rows.map(render)
        : [node('p', emptyText, 'empty')]));
    }
    function renderHistory(row) {
      const article = card(`Retained sample submission: ${row.id}`, [
        ['Student', row.student], ['Activity reference', row.activityId],
        ['Original route', `${row.activityId} → ${row.assignment.id} → ${row.assignment.teacher}`],
        ['Synthetic acceptance timestamp', row.acceptedAt],
        ['Synthetic acceptance operation key', row.acceptanceKey]
      ]);
      article.append(node('h3', 'Original teaching assignment'), details(assignmentPairs(row.assignment)));
      article.append(node('h3', 'Accepted-under enrollment'), details([
        ['Original enrollment ID', row.enrollment.id], ...scope(row.enrollment), ...interval(row.enrollment)
      ]));
      return article;
    }
    function render() {
      const view = project(data, elements.persona.value, elements.scenario.value);
      const name = view.persona ? `${view.persona.name} (${view.persona.role})` : 'Unknown sample persona';
      elements.summary.replaceChildren(
        node('p', name),
        node('p', `Sample parent: ${view.period || 'None'} — ${view.periodState || 'unavailable'}`),
        node('p', view.newWorkNotice)
      );
      panel('current', view.current, 'No current sample scope.', row =>
        card(`Current ${view.persona.role === 'student' ? 'enrollment' : 'assignment'}: ${row.id}`, scope(row)));
      panel('assignments', view.assignments, 'No teaching assignment references for this sample persona.', row =>
        card(`${row.entry} — ${row.id}`, assignmentPairs(row)));
      panel('enrollments', view.enrollments, 'No own sample enrollment history for this persona.', row =>
        card(`Retained enrollment: ${row.id}`, [...scope(row), ...interval(row)]));
      panel('history', view.history, 'No retained sample submissions for this persona.', renderHistory);
      elements.status.textContent = `${name}: ${view.periodState || 'unavailable'} sample parent; ${view.history.length} retained sample submissions. Read-only view updated.`;
    }

    for (const person of data.people) {
      const option = node('option', `${person.name} — ${person.role}`);
      option.value = person.id;
      elements.persona.append(option);
    }
    for (const scenario of data.scenarios) {
      const option = node('option', scenario.name);
      option.value = scenario.id;
      elements.scenario.append(option);
    }
    elements.persona.value = 'student-river';
    elements.scenario.value = 'current';
    elements.persona.addEventListener('change', render);
    elements.scenario.addEventListener('change', render);
    render();
  }

  const api = { mount };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else {
    root.EVALPrototype = api;
    mount(root.document, root.EVALFixtures, root.EVALProjections.project);
  }
})(globalThis);
