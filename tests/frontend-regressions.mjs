import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');

// Execute the real save handler with a failed request, then retry without another edit.
{
  const source = read('admin/write-js.php');
  const handler = source.slice(source.indexOf('Typecho.savePost = function'), source.indexOf('\n    };', source.indexOf('Typecho.savePost = function')) + 7)
    .replace(/<\?php[\s\S]*?\?>/g, '"message"');
  const Typecho = {};
  const requests = [];
  const text = { text() { return this; }, effect() { return this; } };
  const form = { get() { return {}; }, triggerHandler() {}, trigger() {}, attr() { return '/save'; } };
  const state = new Function('Typecho', '$', 'form', 'FormData', 'autoSave', 'idInput', `
    let changed = true, lastSaveTime, cid, draftId;
    ${handler}
    return { dirty: () => changed };
  `)(Typecho, { ajax: (options) => requests.push(options) }, form, class { append() {} }, text, { val() {} });
  let completed = 0;
  Typecho.savePost(() => completed++);
  requests[0].error();
  requests[0].complete();
  assert.equal(state.dirty(), true, 'failed save must retain the unsaved-changes warning');
  Typecho.savePost(() => completed++);
  assert.equal(requests.length, 2, 'retry must send another POST');
  assert.equal(completed, 0, 'failed save must not run a success callback');
  requests[1].success({ time: 'now', cid: 1, draftId: 0 });
  assert.equal(completed, 1);
}

// Clearing the visible URL must also clear the actual submitted hidden field.
{
  const source = read('admin/form-js.php');
  const handler = source.slice(source.indexOf('function setInput()'), source.indexOf('\n            self.removeAttr'));
  let visible = 'https://example.test/old';
  let submitted;
  const setInput = new Function('self', 'input', `${handler}; return setInput;`)(
    { val: () => visible }, { val: (value) => { submitted = value; } });
  setInput();
  assert.equal(submitted, visible);
  visible = '';
  setInput();
  assert.equal(submitted, '', 'empty profile URL was replaced by its old value');
  visible = 'invalid URL';
  setInput();
  assert.equal(submitted, visible, 'invalid input must reach validation rather than restore an old value');
}

// Execute the refresh branch with the real template's list selector and an existing pager.
{
  const source = read('usr/themes/default-2026/src/js/app.js');
  const start = source.indexOf('const refreshComments = async');
  const handler = source.slice(start, source.indexOf('\n  };', start) + 5);
  assert.match(read('usr/themes/default-2026/comments.php'), /class="thread-list/);
  const replaced = [];
  let removed = false;
  const list = { replaceWith: (node) => replaced.push(['list', node]) };
  const nav = { replaceWith: (node) => replaced.push(['pager', node]), remove: () => { removed = true; } };
  const freshList = {};
  const freshNav = {};
  const comments = { querySelector: (selector) => ({ '.thread-list': list, '.page-navigator': nav })[selector] ?? null };
  const incoming = { querySelector: (selector) => ({ '.thread-list': freshList, '.page-navigator': freshNav })[selector] ?? null };
  const refresh = new Function('comments', 'fetch', 'DOMParser', 'document', `
    const respond = null, noticeBox = null;
    const highlightCodeBlocks = () => {}, enhanceNotice = () => {}, enhanceCommentBox = () => {};
    ${handler}
    return refreshComments;
  `)(comments, async () => ({ ok: true, text: async () => '' }),
    class { parseFromString() { return { getElementById: () => incoming }; } },
    { importNode: (node) => node });
  await refresh('/article', null);
  assert.deepEqual(replaced, [['list', freshList], ['pager', freshNav]], 'refresh must replace the actual thread and pager');
  assert.equal(removed, false, 'refresh removed the pager instead of updating comments');
}

console.log('PASS: frontend audit regressions (editor, profile URL, comment refresh)');

// default-docs: index pages are created by PHP once, never re-published or duplicated.
{
  const functions = read('usr/themes/default-docs/functions.php');
  assert.match(functions, /function themeInit/, 'theme must create index pages in PHP');
  assert.match(functions, /transaction\(/, 'index page creation must be atomic');
  assert.doesNotMatch(functions, /transaction\(\s*static function\s*\([^)]*[\w$]/,
    'transaction callback is invoked without arguments, it must not require one');
  assert.match(functions, /default_docs_indexes/, 'creation must be guarded by a one-time marker');
  assert.doesNotMatch(functions, /\$i\+\+/, 'slugs must not be suffixed into duplicate pages');
  const comments = read('usr/themes/default-docs/comments.php');
  for (const field of ['author', 'mail', 'url']) {
    assert.match(comments, new RegExp(`<input[^>]+name="${field}"[^>]+id="${field}"`), `${field} must be a normal input`);
    assert.match(comments, new RegExp(`<label for="${field}">`), `${field} needs a label`);
  }
  assert.match(comments, /<label for="textarea">/);
  assert.doesNotMatch(comments, /textarea\.value\s*=|querySelector\('#author'\)/, 'comment text must not be parsed for identity');
}
console.log('PASS: default-docs regressions (one-time PHP pages, labelled comment fields)');
