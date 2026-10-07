import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const fixtures = require('../fixtures.js');
const { project } = require('../projections.js');
const { mount } = require('../app.js');
const source = name => readFileSync(new URL(`../${name}`, import.meta.url), 'utf8');

// This tiny component double implements only the DOM operations used here.
// It does not model layout, focus, accessibility APIs, or browser execution.
class Element {
  constructor(tagName) {
    this.tagName = tagName;
    this.children = [];
    this.value = '';
    this.listeners = {};
    this.text = '';
  }
  set textContent(value) { this.text = String(value); this.children = []; }
  get textContent() { return this.text + this.children.map(child => child.textContent).join(' '); }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.text = ''; this.children = children; }
  addEventListener(type, callback) { this.listeners[type] = callback; }
  change(value) { this.value = value; this.listeners.change?.(); }
}

function documentDouble() {
  const ids = ['persona', 'scenario', 'summary', 'current', 'assignments', 'enrollments', 'history', 'status'];
  const elements = Object.fromEntries(ids.map(id => [id, new Element(id)]));
  return {
    getElementById: id => elements[id],
    createElement: tag => new Element(tag),
    elements
  };
}

test('disk artifact references only local classic scripts and stylesheet', () => {
  const html = source('index.html');
  assert.deepEqual([...html.matchAll(/<script\s+src="([^"]+)"/g)].map(match => match[1]),
    ['fixtures.js', 'projections.js', 'app.js']);
  assert.match(html, /href="styles.css"/);
  const runtime = ['index.html', 'fixtures.js', 'projections.js', 'app.js', 'styles.css'].map(source).join('\n');
  assert.doesNotMatch(runtime, /https?:|fetch\s*\(|XMLHttpRequest|WebSocket|localStorage|sessionStorage|setInterval|\beval\s*\(|new Function|\bimport\s|@import|type="module"|<form\b/i);
  assert.doesNotMatch(source('app.js'), /innerHTML|insertAdjacentHTML/);
});

test('banner, semantic controls, live status, and responsive focus rules are explicit', () => {
  const html = source('index.html');
  assert.match(html, /EVAL.*Synthetic read-only prototype/);
  assert.match(html, /Persona selection is not authentication or permissions/);
  assert.match(html, /fixtures are inspectable/i);
  assert.match(html, /<main\b/);
  for (const id of ['persona', 'scenario']) {
    assert.match(html, new RegExp(`<label for="${id}">`));
    assert.match(html, new RegExp(`<select id="${id}"`));
  }
  assert.match(html, /id="status"[^>]*aria-live="polite"/);
  assert.match(html, /<noscript>/);
  assert.match(source('styles.css'), /:focus-visible/);
  assert.match(source('styles.css'), /@media/);
});

test('mount shows student current and original history in separate panels', () => {
  const doc = documentDouble();
  mount(doc, fixtures, project);
  const e = doc.elements;
  assert.equal(e.persona.children.length, fixtures.people.length);
  assert.match(e.current.textContent, /Section B/);
  assert.match(e.history.textContent, /Original teaching assignment/);
  assert.match(e.history.textContent, /Morgan Vale/);
  assert.match(e.history.textContent, /Accepted-under enrollment/);
  assert.match(e.history.textContent, /Section A/);
  assert.match(e.history.textContent, /Synthetic operational/);
  assert.doesNotMatch(e.history.textContent, /Sky Rowan|submission-other/);
});

test('persona changes replace stale history and teacher does not inherit original work', () => {
  const doc = documentDouble();
  mount(doc, fixtures, project);
  doc.elements.persona.change('teacher-morgan');
  assert.match(doc.elements.history.textContent, /submission-river/);
  doc.elements.persona.change('teacher-jules');
  assert.match(doc.elements.history.textContent, /No retained sample submissions/);
  assert.doesNotMatch(doc.elements.history.textContent, /submission-river|Morgan Vale/);
  assert.match(doc.elements.assignments.textContent, /assignment-new/);
  assert.match(doc.elements.assignments.textContent, /assignment-jules-b/);
});

test('closed scenario keeps child state, explains no work, and updates live status', () => {
  const doc = documentDouble();
  mount(doc, fixtures, project);
  doc.elements.scenario.change('closed');
  assert.match(doc.elements.summary.textContent, /No new academic work/);
  assert.match(doc.elements.current.textContent, /active/);
  assert.match(doc.elements.history.textContent, /submission-river/);
  assert.match(doc.elements.status.textContent, /River Linden.*closed/);
});

test('empty and unknown personas clear records; text is rendered as data', () => {
  const data = structuredClone(fixtures);
  data.people.find(person => person.id === 'student-river').name = '<img src=x onerror=alert(1)>';
  const doc = documentDouble();
  mount(doc, data, project);
  assert.match(doc.elements.summary.textContent, /<img src=x onerror=alert\(1\)>/);
  assert.equal(doc.elements.summary.children[0].tagName, 'p');
  for (const id of ['student-empty', 'teacher-empty', 'unknown']) {
    doc.elements.persona.change(id);
    assert.match(doc.elements.current.textContent, /No current sample scope/);
    assert.match(doc.elements.history.textContent, /No retained sample submissions/);
    assert.doesNotMatch(doc.elements.history.textContent, /submission-river/);
  }
});
