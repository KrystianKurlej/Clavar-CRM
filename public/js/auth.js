async function loginSubmit(form){
    const data = Object.fromEntries(new FormData(form));

    try {
        const res = await fetch(form.action, { method:'POST', body: JSON.stringify(data), headers: { 'Content-Type': 'application/json' }, credentials:'include' });
        const json = await res.json();
        if (json && json.ok) { location.href = '/projects'; return false; }
    } catch(e){}

    location.reload();
    return false;
}

async function logoutSubmit(form){
    try {
      const data = new FormData(form);
      await fetch(form.action, { method:'POST', body: JSON.stringify(Object.fromEntries(data)), headers: { 'Content-Type': 'application/json' }, credentials:'include' });
    } catch(e){}
    
    location.href = '/login';
    return false;
}