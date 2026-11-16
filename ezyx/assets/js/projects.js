const CSRF_TOKEN = window.CSRF_TOKEN || '';
function apiFetch(url, opts){
  opts = opts || {};
  opts.headers = Object.assign({'X-CSRF-Token': CSRF_TOKEN}, opts.headers || {});
  return fetch(url, opts);
}

function createProject(){
  var name=prompt('Название проекта:','Новая структура');
  if(!name)return;
  apiFetch('api.php?action=project_create',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:name})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.success){window.location.href='/editor?project='+d.id;}
      else{alert('Ошибка: '+(d.error||'неизвестная'));}
    })
    .catch(function(e){alert('Network error: '+e.message)});
}
function deleteProject(id){
  if(!confirm('Удалить проект со всеми сотрудниками? Это действие необратимо.'))return;
  apiFetch('api.php?action=project_delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:parseInt(id,10)})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.success){location.reload();}
      else{alert('Error: '+(d.error||'unknown'));}
    })
    .catch(function(e){alert('Network error: '+e.message)});
}
function duplicateProject(id){
  var btn=window.event&&window.event.target?window.event.target.closest('button'):null;
  if(btn)btn.disabled=true;
  apiFetch('api.php?action=project_duplicate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:parseInt(id,10)})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.success){location.reload();}
      else{alert('Error: '+(d.error||'unknown'));if(btn)btn.disabled=false;}
    })
    .catch(function(e){alert('Ошибка сети: '+e.message);if(btn)btn.disabled=false;});
}