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

// default-docs: the theme claims /categories.html, /archives.html, /tags.html itself; it writes nothing.
{
  const functions = read('usr/themes/default-docs/functions.php');
  assert.match(functions, /function themeInit\(\\Widget\\Archive \$archive\): void/, 'index pages must be served by themeInit');
  assert.match(functions, /\$archive->need\('page-' \. \$slug \. '\.php'\)/, 'themeInit must render the existing index templates');
  assert.match(functions, /\$archive->response->setStatus\(200\)/, 'a claimed index page must not answer 404');
  assert.match(functions, /function docswebIndexUrl\(string \$slug\): string/, 'index links must be built, not read from the database');
  assert.doesNotMatch(functions, /insert\('table\.(contents|options)'\)/, 'theme must not write pages or settings');
  assert.doesNotMatch(functions, /table\.contents/, 'theme must not look up index pages in the database');
  const comments = read('usr/themes/default-docs/comments.php');
  // 身份写在 textarea 前三行 (Name:/Email:/Website:), 真正的 input 隐藏, 提交时再拆回去
  for (const field of ['author', 'mail', 'url']) {
    assert.match(comments, new RegExp(`<input[^>]+name="${field}"[^>]+id="${field}"`), `${field} must be a normal input`);
    assert.match(comments, new RegExp(`\\$this->remember\\('${field}'`), `${field} must be prefilled from the remember cookie`);
  }
  assert.match(comments, /<label for="textarea">/);
  assert.doesNotMatch(comments, /<label for="(author|mail|url)">/, 'identity fields are hidden, they need no labels');
  assert.doesNotMatch(comments, /Name' => 'author/, 'PHP must not print the identity lines into the comment text');
  assert.match(comments, /identity\.push\(/, 'the script must build the identity lines');
  assert.match(comments, /identity\.join\('\\n'\)/, 'identity lines must keep the Name:/Email:/Website: layout');
  assert.match(comments, /form\[fields\[field\]\]\.value = map\[field\]/, 'identity lines must be split into the hidden inputs on submit');
  assert.match(comments, /lines\.slice\(i\)\.join/, 'identity lines must be stripped from the comment text');
}
console.log('PASS: default-docs regressions (claimed index pages, identity lines in comment text)');
