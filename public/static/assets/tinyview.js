// TinyView: ultra-light includes + loops for static HTML, no build step
// Usage:
//  - <div data-include="/static/partials/nav.html"></div>
//  - <ul data-each="item in items"><li>{{item.name}}</li></ul>
//  - set data on window.TINYVIEW = { items: [...] }
(function(){
  const TV = window.TINYVIEW = window.TINYVIEW || {};

  async function fetchText(url){
    const res = await fetch(url, { credentials: 'include' });
    if(!res.ok) throw new Error('Failed to fetch '+url);
    return await res.text();
  }

  function renderTemplate(tpl, scope){
    return tpl.replace(/{{\s*([\w$.]+)\s*}}/g, (_, path) => {
      const parts = path.split('.');
      let val = scope;
      for (const p of parts) { if (val && Object.prototype.hasOwnProperty.call(val, p)) { val = val[p]; } else { return ''; } }
      return String(val ?? '');
    });
  }

  function getByPath(scope, path){
    const parts = path.split('.');
    let val = scope;
    for(const p of parts){ val = val?.[p]; }
    return val;
  }

  async function processIncludes(root){
    const nodes = root.querySelectorAll('[data-include]');
    for(const el of nodes){
      const url = el.getAttribute('data-include');
      if(!url) continue;
      const html = await fetchText(url);
      el.innerHTML = html;
    }
  }

  function processLoops(root, data){
    const loops = root.querySelectorAll('[data-each]');
    for(const el of loops){
      const expr = el.getAttribute('data-each'); // e.g. "item in items"
      if(!expr) continue;
      const m = expr.match(/^\s*(\w+)\s+in\s+([\w$.]+)\s*$/);
      if(!m) continue;
      const [, varName, collPath] = m;
      const coll = getByPath(data, collPath) || [];
      const tpl = el.innerHTML;
      let out = '';
      for(const item of coll){
        const scope = Object.assign({}, data); // shallow
        scope[varName] = item;
        out += renderTemplate(tpl, scope);
      }
      el.innerHTML = out;
    }
  }

  async function init(){
    const data = TV.data || {};
    await processIncludes(document);
    processLoops(document, data);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
