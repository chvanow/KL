const CSRF_TOKEN = window.CSRF_TOKEN || '';
function apiFetch(url, opts){
  opts = opts || {};
  opts.headers = Object.assign({'X-CSRF-Token': CSRF_TOKEN}, opts.headers || {});
  return fetch(url, opts);
}
const api = a => `api.php?action=${a}&project=${PROJECT_ID}`;
let scale=1,panX=0,panY=0;
try{var _v=JSON.parse(localStorage.getItem('view_'+PROJECT_ID)||'{}');if(typeof _v.panX==='number'){panX=_v.panX;panY=_v.panY;scale=_v.scale||1;}}catch(e){}

let employees=[],selected=null,dragging=null,dragOffset={x:0,y:0},connecting=null,tempLine=null,clipboard=null,isPanning=false,panStart={x:0,y:0},isDraggingCrop=false,cropStartX,cropStartY,initCropX,initCropY,cropData={x:0,y:0,z:1,img:''},autosaveTimer=null;
let offices=[];
let selectedOfficeColor='#4D668E';
let linksData=[];
let linkEditIndex=-1;
const canvas=document.getElementById('canvas'),container=document.getElementById('container'),svg=document.getElementById('svg');
const CROP_RATIO=4;
const toLocal=ev=>{const r=canvas.getBoundingClientRect();return{x:(ev.clientX-r.left-panX)/scale,y:(ev.clientY-r.top-panY)/scale}};
const updateTransform=()=>{container.style.transform=`translate(${panX}px, ${panY}px) scale(${scale})`;try{localStorage.setItem('view_'+PROJECT_ID,JSON.stringify({panX,panY,scale}));}catch(e){}};
const esc=s=>String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
const cardEl=id=>document.querySelector(`.card[data-id="${id}"]`);

async function flushPositions(){
  const pos=employees.map(e=>{const c=cardEl(e.id);
    return{id:e.id,position_x:c?Math.round(parseFloat(c.style.left)||0):e.position_x,position_y:c?Math.round(parseFloat(c.style.top)||0):e.position_y,width:e.width,height:e.height,parent_id:e.parent_id};
  });
  try{await apiFetch(api('save'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({employees:pos})});}catch(err){console.error('autosave',err)}
}
function scheduleAutosave(){if(autosaveTimer)clearTimeout(autosaveTimer);autosaveTimer=setTimeout(flushPositions,100);}
function setupSvg(){if(!svg)return;svg.style.cssText='position:absolute;top:0;left:0;width:10000px;height:10000px;pointer-events:none;z-index:1;overflow:visible'}
document.addEventListener('DOMContentLoaded',()=>{setupSvg();updateTransform();load()});

async function load(){
  try{
    const d=await(await apiFetch(api('get'))).json();
    if(d.error)throw new Error(d.error);
    employees=Array.isArray(d)?d:[];
employees.forEach(e=>{
  ['position_x','position_y','width','height','parent_id','office_id','list_order','z_index'].forEach(k=>e[k]=parseInt(e[k])||0);
      e.show_contacts=(e.show_contacts==0)?0:1;
      e.show_office=(e.show_office==1)?1:0;
      e.line_offsets=parseLineOffsets(e.line_offsets);
    });
    try{const od=await(await apiFetch(api('office_list'))).json();offices=Array.isArray(od)?od:[];}catch(e){offices=[];}
    render();drawLines();
  }catch(e){console.error(e);alert('Ошибка загрузки: '+e.message)}
}
function parseLineOffsets(v){
  if(!v)return null;
  if(typeof v==='object')return v;
  try{const o=JSON.parse(v);return (o&&typeof o==='object')?o:null;}catch(e){return null;}
}
function render(){
  const ids=new Set(employees.map(e=>String(e.id)));
  container.querySelectorAll('.card').forEach(c=>{if(!ids.has(c.dataset.id))c.remove()});
  employees.forEach(e=>{if(!cardEl(e.id))container.appendChild(createCard(e));else updateCard(cardEl(e.id),e);});
}
function createCard(e){
  const d=document.createElement('div');
  d.dataset.id=e.id;
  d.innerHTML=buildCardInner(e)+
    `<div class="point top" data-side="top"></div><div class="point right" data-side="right"></div>
     <div class="point bottom" data-side="bottom"></div><div class="point left" data-side="left"></div>
     <div class="resize"></div>`;
  d.addEventListener('mousedown',startDrag);
  d.addEventListener('dblclick',()=>edit(e.id));
  d.addEventListener('contextmenu',ev=>{ev.preventDefault();showMenu(ev,e.id)});
  d.addEventListener('click',ev=>{if(!ev.target.classList.contains('resize')&&!ev.target.classList.contains('point'))select(e.id)});
  d.querySelectorAll('.point').forEach(p=>p.addEventListener('mousedown',ev=>startConnect(ev,e.id,p.dataset.side)));
  d.querySelector('.resize').addEventListener('mousedown',startResize);
  updateCard(d,e);
  return d;
}
function buildCardInner(e){
  const photo=(e.show_photo&&e.photo)?`<div class="photo"><img src="/${e.photo}" style="transform:scale(${e.crop_zoom||1}) translate(${e.crop_x||0}px,${e.crop_y||0}px)" onerror="this.style.display='none'"></div>`:'';
  const dept=(e.is_department&&e.department)?`<div class="dept-badge">${esc(e.department)}</div>`:'';
  let contacts='';
  if(e.show_contacts!=0){
    const phones=(e.phone||'').split('|').filter(Boolean);
    const emails=(e.email||'').split('|').filter(Boolean);
    const ext=e.ext?String(e.ext).trim():'';
    if(phones.length||emails.length||ext){
      contacts='<div class="contact-lines">';
      phones.forEach(p=>{contacts+=`<div class="contact-line"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92z"/></svg>${esc(p)}</div>`;});
      if(ext){contacts+=`<div class="contact-line">доб. ${esc(ext)}</div>`;}
      emails.forEach(m=>{contacts+=`<div class="contact-line"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>${esc(m)}</div>`;});
      contacts+='</div>';
    }
  }
  let officeHtml='';
  if(e.show_office==1){
    if(e.office_id){
      const off=offices.find(o=>o.id==e.office_id);
      if(off){const cab=e.cabinet?' (каб. '+esc(e.cabinet)+')':'';officeHtml=`<div class="office-badge" style="background:${off.color}">${esc(off.name)}${cab}</div>`;}
    }else if(e.cabinet){officeHtml=`<div class="office-badge" style="background:#888">каб. ${esc(e.cabinet)}</div>`;}
  }
  return dept+photo+`<div class="info"><div class="name">${esc(e.full_name)}</div><div class="position">${esc(e.position)}</div>${contacts}${officeHtml}</div>`;
}
function updateCard(c,e){
  c.className='card'+(e.show_photo&&e.photo?' has-photo':'')+(e.is_department?' has-dept':'');
  c.style.left=e.position_x+'px';c.style.top=e.position_y+'px';
  c.style.width=e.width+'px';c.style.height=e.height+'px';
  c.querySelectorAll('.dept-badge,.photo,.info').forEach(x=>x.remove());
  c.insertAdjacentHTML('afterbegin',buildCardInner(e));
  if(e.z_index!==undefined && e.z_index!==null) c.style.zIndex=e.z_index;
}
canvas.addEventListener('mousedown',e=>{
  if(e.button!==0) return;
  if(e.target===canvas){
    isPanning=true;
    panStart={x:e.clientX-panX,y:e.clientY-panY};
    canvas.classList.add('panning');
    clipboard=null;
    e.preventDefault();
  }
});

document.addEventListener('mousemove',e=>{
  if(isPanning){
    panX=e.clientX-panStart.x;
    panY=e.clientY-panStart.y;
    updateTransform();
  }
  if(dragging&&!isPanning){
    const p=toLocal(e);let x=Math.round(p.x-dragOffset.x),y=Math.round(p.y-dragOffset.y);
    if(x<0)x=0;if(y<0)y=0;
    dragging.style.left=x+'px';dragging.style.top=y+'px';
    const emp=employees.find(v=>v.id===parseInt(dragging.dataset.id));
    if(emp){emp.position_x=x;emp.position_y=y}
    drawLines();
  }
  if(connecting&&tempLine){const p=toLocal(e);tempLine.setAttribute('x2',p.x);tempLine.setAttribute('y2',p.y)}
  if(isDraggingCrop){cropData.x=initCropX+(e.clientX-cropStartX);cropData.y=initCropY+(e.clientY-cropStartY);applyCropVisual()}
  if(_lineDrag.active){_onLineDragMove(e);}
});

document.addEventListener('mouseup',e=>{
  if(isPanning){isPanning=false;canvas.classList.remove('panning')}
  if(dragging){dragging=null;scheduleAutosave()}
  if(connecting){const el=document.elementFromPoint(e.clientX,e.clientY),card=el?el.closest('.card'):null;if(card){const tid=parseInt(card.dataset.id);if(tid!==connecting.id&&!createsCycle(connecting.id,tid))connect(connecting.id,tid);else if(tid!==connecting.id)alert('Нельзя создать цикл')}removeTempLine();connecting=null}
  if(isDraggingCrop)isDraggingCrop=false;
  if(_lineDrag.active){_endLineDrag();}
});

// Сброс при потере фокуса окна
window.addEventListener('blur',()=>{
  if(isPanning){isPanning=false;canvas.classList.remove('panning');}
  if(dragging){dragging=null;scheduleAutosave();}
  if(connecting){removeTempLine();connecting=null;}
  if(isDraggingCrop)isDraggingCrop=false;
  if(_lineDrag.active){_endLineDrag();}
});

// Сброс при уходе мыши за пределы документа
document.addEventListener('mouseleave',()=>{
  if(isPanning){isPanning=false;canvas.classList.remove('panning');}
  if(dragging){dragging=null;scheduleAutosave();}
});
document.addEventListener('mouseup',e=>{
  if(isPanning){isPanning=false;canvas.classList.remove('panning')}
  if(dragging){dragging=null;scheduleAutosave()}
  if(connecting){const el=document.elementFromPoint(e.clientX,e.clientY),card=el?el.closest('.card'):null;if(card){const tid=parseInt(card.dataset.id);if(tid!==connecting.id&&!createsCycle(connecting.id,tid))connect(connecting.id,tid);else if(tid!==connecting.id)alert('Нельзя создать цикл')}removeTempLine();connecting=null}
  if(isDraggingCrop)isDraggingCrop=false;
  if(_lineDrag.active){_endLineDrag();}
});
function startDrag(e){if(e.target.classList.contains('resize')||e.target.classList.contains('point'))return;e.preventDefault();e.stopPropagation();dragging=e.currentTarget;select(parseInt(dragging.dataset.id));const p=toLocal(e);dragOffset={x:p.x-parseFloat(dragging.style.left||0),y:p.y-parseFloat(dragging.style.top||0)}}
function startResize(e){
  e.stopPropagation();e.preventDefault();
  const card=e.target.closest('.card'),id=parseInt(card.dataset.id),emp=employees.find(v=>v.id===id);
  const s=toLocal(e),sw=card.offsetWidth,sh=card.offsetHeight;
  const move=ev=>{const p=toLocal(ev),w=Math.max(120,Math.round(sw+p.x-s.x)),h=Math.max(60,Math.round(sh+p.y-s.y));card.style.width=w+'px';card.style.height=h+'px';if(emp){emp.width=w;emp.height=h}drawLines()};
  const stop=()=>{document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',stop);scheduleAutosave()};
  document.addEventListener('mousemove',move);document.addEventListener('mouseup',stop);
}
function startConnect(e,id,side){
  e.stopPropagation();e.preventDefault();connecting={id,side};
  const emp=employees.find(v=>v.id===id);if(!emp)return;
  const a=getAnchor(emp,side);
  const line=document.createElementNS('http://www.w3.org/2000/svg','line');
  line.setAttribute('x1',a.x);line.setAttribute('y1',a.y);line.setAttribute('x2',a.x);line.setAttribute('y2',a.y);
  line.setAttribute('stroke','rgba(100,180,220,.9)');line.setAttribute('stroke-width','2');
  line.setAttribute('stroke-dasharray','5,5');line.setAttribute('pointer-events','none');
  svg.appendChild(line);tempLine=line;document.body.style.cursor='crosshair';
}
function removeTempLine(){svg.querySelectorAll('line').forEach(l=>l.remove());tempLine=null;document.body.style.cursor=''}
function getAnchor(emp,side){const c=cardEl(emp.id),w=c?c.offsetWidth:emp.width,h=c?c.offsetHeight:emp.height,x=emp.position_x,y=emp.position_y;return{top:{x:x+w/2,y},right:{x:x+w,y:y+h/2},bottom:{x:x+w/2,y:y+h},left:{x,y:y+h/2}}[side]||{x:x+w/2,y:y+h/2}}
async function connect(from,to){const emp=employees.find(x=>x.id===from);if(!emp)return;emp.parent_id=to;emp.line_offsets=null;await flushPositions();await apiFetch(api('update'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:emp.id,parent_id:to,line_offsets:null})});await load();}
function createsCycle(child,parent){let cur=parent;const seen=new Set();while(cur&&!seen.has(cur)){if(cur===child)return true;seen.add(cur);const e=employees.find(v=>v.id===cur);cur=e?e.parent_id:0}return false}
function select(id){selected=employees.find(e=>e.id===id)||null;document.querySelectorAll('.card').forEach(c=>c.classList.remove('selected'));const c=cardEl(id);if(c)c.classList.add('selected')}
function bringToFront(id){
  const c=cardEl(id);if(!c)return;
  // z-index = максимум среди всех карточек + 1
  let maxZ=10;
  document.querySelectorAll('#container .card').forEach(el=>{
    const z=parseInt(el.style.zIndex)||10;
    if(z>maxZ)maxZ=z;
  });
  c.style.zIndex=maxZ+1;
  document.getElementById('contextMenu').style.display='none';
  // сохраняем в БД (поле z_index, см. шаг 3)
  saveZIndex(id,maxZ+1);
}
function sendToBack(id){
  const c=cardEl(id);if(!c)return;
  // z-index = минимум среди всех карточек - 1
  let minZ=10;
  document.querySelectorAll('#container .card').forEach(el=>{
    const z=parseInt(el.style.zIndex)||10;
    if(z<minZ)minZ=z;
  });
  const newZ=minZ-1;
  c.style.zIndex=newZ;
  document.getElementById('contextMenu').style.display='none';
  saveZIndex(id,newZ);
}
async function saveZIndex(id,z){
  // сохраняем в БД, чтобы после перезагрузки порядок сохранился
  try{
    await apiFetch(api('update'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,z_index:z})});
    const emp=employees.find(v=>v.id===id);
    if(emp)emp.z_index=z;
  }catch(e){console.error('saveZIndex',e);}
}
function showMenu(e,id){
  const m=document.getElementById('contextMenu');m.innerHTML='';
  m.innerHTML+=`<div onclick="editSelected()">Редактировать</div><div onclick="copyBlock(${id})">Копировать</div>`;
  if(clipboard)m.innerHTML+=`<div onclick="pasteBlock(${e.pageX},${e.pageY})">Вставить</div>`;
  m.innerHTML+=`<div onclick="bringToFront(${id})">На передний план</div>`;
  m.innerHTML+=`<div onclick="sendToBack(${id})">На задний план</div>`;
  m.innerHTML+=`<div onclick="deleteSelected()">Удалить</div>`;
  m.style.cssText=`display:block;left:${e.pageX}px;top:${e.pageY}px`;
  selected=employees.find(v=>v.id===id)||null;
  setTimeout(()=>document.addEventListener('click',()=>m.style.display='none',{once:true}),100);
}
const editSelected=()=>{if(selected)edit(selected.id);document.getElementById('contextMenu').style.display='none'};
const deleteSelected=()=>{if(selected&&confirm('Удалить блок? Связи будут разорваны.')){deleteEmployee(selected.id);document.getElementById('contextMenu').style.display='none'}};
const copyBlock=id=>{clipboard=employees.find(e=>e.id===id);document.getElementById('contextMenu').style.display='none'};
async function pasteBlock(x,y){
  if(!clipboard)return;
  const r=canvas.getBoundingClientRect();
  const data=Object.assign({},clipboard,{full_name:clipboard.full_name+' (копия)',position_x:Math.round((x-r.left)/scale-panX),position_y:Math.round((y-r.top)/scale-panY),parent_id:0,line_offsets:null,crop_x:0,crop_y:0,crop_zoom:1});
  delete data.id;delete data.created_at;delete data.updated_at;delete data.project_id;
  await flushPositions();
  await apiFetch(api('create'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  clipboard=null;document.getElementById('contextMenu').style.display='none';
  await load();
}
const addEmployee=()=>openModal();
const edit=id=>openModal(employees.find(e=>e.id===id));
function formatPhone(inp){let v=inp.value.replace(/\D/g,'');if(v.startsWith('8'))v='7'+v.substring(1);if(!v.startsWith('7'))v='7'+v;let f='+7';if(v.length>1)f+=' ('+v.substring(1,4);if(v.length>=4)f+=') '+v.substring(4,7);if(v.length>=7)f+='-'+v.substring(7,9);if(v.length>=9)f+='-'+v.substring(9,11);inp.value=f}
function renderContactInputs(type,values){
  const c=document.getElementById(type+'-container');c.innerHTML='';
  values.forEach((v,i)=>{const row=document.createElement('div');row.className='contact-row';
    const inp=document.createElement('input');inp.type=type==='phone'?'text':'email';inp.name=type;inp.value=v;
    inp.placeholder=type==='phone'?'+7 (___) ___-__-__':'Email';
    if(type==='phone')inp.addEventListener('input',()=>formatPhone(inp));row.appendChild(inp);
    if(i>0){const b=document.createElement('button');b.type='button';b.className='remove-contact';b.innerHTML='×';b.onclick=()=>{row.remove();updateAddButtons()};row.appendChild(b)}
    c.appendChild(row)});
  updateAddButtons();
}
function updateAddButtons(){['phone','email'].forEach(t=>{const n=document.querySelectorAll(`[name="${t}"]`).length;const b=document.getElementById('add-'+t);if(b)b.style.display=n>=3?'none':'inline-block'})}
function addContact(type){
  const c=document.getElementById(type+'-container');if(c.children.length>=3)return;
  const row=document.createElement('div');row.className='contact-row';
  const inp=document.createElement('input');inp.type=type==='phone'?'text':'email';inp.name=type;
  inp.placeholder=type==='phone'?'+7 (___) ___-__-__':'Email';
  if(type==='phone')inp.addEventListener('input',()=>formatPhone(inp));row.appendChild(inp);
  const b=document.createElement('button');b.type='button';b.className='remove-contact';b.innerHTML='×';
  b.onclick=()=>{row.remove();updateAddButtons()};row.appendChild(b);
  c.appendChild(row);updateAddButtons();
}
function parseLinks(str){if(!str)return [];return String(str).split('|').filter(Boolean).map(function(item){const parts=item.split('@@');return {url:parts[0]||'',label:parts[1]||''};});}
function serializeLinks(){return linksData.filter(function(l){return l.url;}).map(function(l){return l.url+(l.label?'@@'+l.label:'');}).join('|');}
function renderLinksInputs(){
  const c=document.getElementById('links-container');if(!c)return;
  c.innerHTML='';
  linksData.forEach(function(item,i){
    const row=document.createElement('div');row.className='link-row';
    const inp=document.createElement('input');inp.type='url';inp.value=item.url||'';
    inp.placeholder='https://example.com';
    inp.addEventListener('input',function(){linksData[i].url=inp.value.trim();});
    row.appendChild(inp);
    const edit=document.createElement('button');edit.type='button';edit.className='link-edit';edit.title='Название ссылки';edit.innerHTML='⚙';
    edit.onclick=function(){openLinkModal(i);};
    row.appendChild(edit);
    if(i>0){const b=document.createElement('button');b.type='button';b.className='remove-contact';b.innerHTML='×';b.onclick=function(){linksData.splice(i,1);renderLinksInputs();updateAddLinkBtn();};row.appendChild(b);}
    c.appendChild(row);
  });
  updateAddLinkBtn();
}
function updateAddLinkBtn(){const b=document.getElementById('add-link');if(!b)return;b.style.display=linksData.length>=3?'none':'inline-block';}
function addLink(){if(linksData.length>=3)return;linksData.push({url:'',label:''});renderLinksInputs();const c=document.getElementById('links-container');const inp=c.querySelector('.link-row:last-child input');if(inp)inp.focus();}
function openLinkModal(i){linkEditIndex=i;document.getElementById('linkLabel').value=linksData[i].label||'';document.getElementById('linkModal').classList.add('show-flex');}
function closeLinkModal(){document.getElementById('linkModal').classList.remove('show-flex');}
function applyLinkLabel(){if(linkEditIndex>=0&&linksData[linkEditIndex]){linksData[linkEditIndex].label=document.getElementById('linkLabel').value.trim();}closeLinkModal();}
function clearLinkLabel(){if(linkEditIndex>=0&&linksData[linkEditIndex]){linksData[linkEditIndex].label='';}document.getElementById('linkLabel').value='';}
function fillOfficeSelect(){const s=document.getElementById('officeSelect');if(!s)return;s.innerHTML='<option value="0">Не указан</option>';offices.forEach(function(o){const opt=document.createElement('option');opt.value=o.id;opt.textContent=o.name;s.appendChild(opt);});}
function renderOfficeList(){
  const c=document.getElementById('officeList');if(!c)return;
  if(!offices.length){c.innerHTML='<div style="color:#999;font-size:12px">Пока нет офисов</div>';return;}
  c.innerHTML=offices.map(function(o){
    return '<div class="office-list-item">'
      +'<span class="office-list-color" style="background:'+o.color+'"></span>'
      +'<span class="office-list-name">'+esc(o.name)+'</span>'
      +'<button type="button" onclick="editOffice('+o.id+')" title="Редактировать"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></button>'
      +'<button type="button" class="del" onclick="deleteOffice('+o.id+')" title="Удалить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>'
      +'</div>';
  }).join('');
}
function openOfficeModal(){
  document.getElementById('officeModalTitle').textContent='Новый офис';
  document.getElementById('officeEditId').value='';
  document.getElementById('officeName').value='';
  selectedOfficeColor='#4D668E';
  document.querySelectorAll('#officeColors .color-swatch').forEach(function(s){s.classList.toggle('selected',s.dataset.color===selectedOfficeColor);});
  renderOfficeList();
  document.getElementById('officeModal').classList.add('show-flex');
}
function editOffice(id){
  const o=offices.find(function(x){return x.id==id;});if(!o)return;
  document.getElementById('officeModalTitle').textContent='Редактирование офиса';
  document.getElementById('officeEditId').value=o.id;
  document.getElementById('officeName').value=o.name;
  selectedOfficeColor=o.color||'#4D668E';
  document.querySelectorAll('#officeColors .color-swatch').forEach(function(s){s.classList.toggle('selected',s.dataset.color===selectedOfficeColor);});
}
function closeOfficeModal(){document.getElementById('officeModal').classList.remove('show-flex');}
function saveOffice(){
  const name=document.getElementById('officeName').value.trim();
  if(!name){alert('Введите название офиса');return;}
  const editId=document.getElementById('officeEditId').value;
  const action=editId?'office_update':'office_create';
  const payload={name:name,color:selectedOfficeColor};
  if(editId)payload.id=parseInt(editId);
  fetch(api(action),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.success){
        if(editId){const o=offices.find(function(x){return x.id==d.id;});if(o){o.name=d.name;o.color=d.color;}}
        else{offices.push({id:d.id,name:d.name,color:d.color});}
        fillOfficeSelect();
        if(!editId)document.getElementById('officeSelect').value=d.id;
        document.getElementById('officeEditId').value='';
        document.getElementById('officeName').value='';
        document.getElementById('officeModalTitle').textContent='Новый офис';
        renderOfficeList();
        load();
      }else{alert('Ошибка: '+(d.error||'неизвестная'));}
    }).catch(function(e){alert('Ошибка сети: '+e.message);});
}
function deleteOffice(id){
  if(!confirm('Удалить офис? У сотрудников он будет сброшен.'))return;
  fetch(api('office_delete'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.success){offices=offices.filter(function(o){return o.id!=id;});fillOfficeSelect();renderOfficeList();load();}
      else{alert('Ошибка: '+(d.error||'неизвестная'));}
    }).catch(function(e){alert('Ошибка сети: '+e.message);});
}
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('#officeColors .color-swatch').forEach(function(s){
    s.addEventListener('click',function(){
      selectedOfficeColor=s.dataset.color;
      document.querySelectorAll('#officeColors .color-swatch').forEach(function(x){x.classList.remove('selected');});
      s.classList.add('selected');
    });
  });
});
function openModal(emp){
  const m=document.getElementById('modal'),form=document.getElementById('form');
  form.reset();form.querySelector('[name="id"]').value='';
  ['full_name','position','department','ext','birthday'].forEach(n=>form.querySelector(`[name="${n}"]`).value='');
  ['is_department','show_photo','show_contacts','show_office'].forEach(n=>form.querySelector(`[name="${n}"]`).checked=false);
  form.querySelector('[name="show_in_list"]').checked=true;
  form.querySelector('[name="show_manager"]').checked=true;
  form.querySelector('[name="department"]').parentElement.style.display='none';
  document.getElementById('preview').innerHTML='';
  document.getElementById('btnDelete').style.display='none';
  updateParentSelect(emp?emp.id:null);
  fillOfficeSelect();
  if(emp){
    form.querySelector('[name="id"]').value=emp.id;
    form.querySelector('[name="full_name"]').value=emp.full_name||'';
    form.querySelector('[name="position"]').value=emp.position||'';
    form.querySelector('[name="is_department"]').checked=!!emp.is_department;
    form.querySelector('[name="department"]').value=emp.department||'';
    form.querySelector('[name="department"]').parentElement.style.display=emp.is_department?'block':'none';
    form.querySelector('[name="show_photo"]').checked=!!emp.show_photo;
    form.querySelector('[name="show_contacts"]').checked=(emp.show_contacts!=0);
    form.querySelector('[name="show_office"]').checked=(emp.show_office==1);
    form.querySelector('[name="show_in_list"]').checked=(emp.show_in_list==0)?false:true;
    form.querySelector('[name="show_manager"]').checked=(emp.show_manager==0)?false:true;
    form.querySelector('[name="ext"]').value=emp.ext||'';
    form.querySelector('[name="birthday"]').value=emp.birthday?formatDateRu(emp.birthday):'';
    renderContactInputs('phone',emp.phone?emp.phone.split('|').filter(Boolean):['']);
    renderContactInputs('email',emp.email?emp.email.split('|').filter(Boolean):['']);
    document.getElementById('preview').innerHTML=emp.photo?`<img src="/${emp.photo}"><span class="remove-photo" onclick="removePhoto()">×</span><button type="button" class="btn-crop" onclick="openCropModal('/${emp.photo}',${emp.crop_x||0},${emp.crop_y||0},${emp.crop_zoom||1})">Настроить фото</button>`:'';
    const del=document.getElementById('btnDelete');del.style.display='inline-block';del.onclick=()=>deleteEmployee(emp.id);
    form.querySelector('[name="parent_id"]').value=emp.parent_id||0;
    form.querySelector('[name="office_id"]').value=emp.office_id||0;
    form.querySelector('[name="cabinet"]').value=emp.cabinet||'';
    linksData=parseLinks(emp.links);renderLinksInputs();
  }else{
    renderContactInputs('phone',['']);renderContactInputs('email',['']);
    form.querySelector('[name="parent_id"]').value=0;
    form.querySelector('[name="office_id"]').value=0;
    form.querySelector('[name="cabinet"]').value='';
    form.querySelector('[name="show_contacts"]').checked=true;
    form.querySelector('[name="show_office"]').checked=false;
    linksData=[];renderLinksInputs();
  }
  form.querySelector('[name="is_department"]').onchange=function(){form.querySelector('[name="department"]').parentElement.style.display=this.checked?'block':'none'};
  m.classList.add('show-flex');
}
const closeModal=()=>document.getElementById('modal').classList.remove('show-flex');
const removePhoto=()=>{document.querySelector('[name="photo"]').value='';document.getElementById('preview').innerHTML=''};
document.querySelector('[name="photo"]').addEventListener('change',function(e){const file=e.target.files[0];if(!file)return;const r=new FileReader();r.onload=ev=>{document.getElementById('preview').innerHTML=`<img src="${ev.target.result}"><span class="remove-photo" onclick="removePhoto()">×</span><button type="button" class="btn-crop" onclick="openCropModal('${ev.target.result}',0,0,1)">Настроить фото</button>`};r.readAsDataURL(file)});
function openCropModal(path,x,y,z){
  z=parseFloat(z);if(!isFinite(z)||z<0.5||z>3)z=1;
  x=parseInt(x)||0;y=parseInt(y)||0;
  cropData={x:x*CROP_RATIO,y:y*CROP_RATIO,z:z,img:path};
  document.getElementById('cropImg').src=path;
  document.getElementById('cropZoom').value=z;
  applyCropVisual();
  document.getElementById('cropModal').classList.add('show-flex');
}
const closeCropModal=()=>document.getElementById('cropModal').classList.remove('show-flex');
const applyCropVisual=()=>{document.getElementById('cropImg').style.transform=`scale(${cropData.z}) translate(${cropData.x}px,${cropData.y}px)`};
function resetCrop(){cropData.x=0;cropData.y=0;cropData.z=1;document.getElementById('cropZoom').value=1;applyCropVisual()}
document.querySelector('.crop-container').addEventListener('mousedown',e=>{e.preventDefault();isDraggingCrop=true;cropStartX=e.clientX;cropStartY=e.clientY;initCropX=cropData.x;initCropY=cropData.y});
document.getElementById('cropZoom').addEventListener('input',e=>{cropData.z=parseFloat(e.target.value);applyCropVisual()});
document.getElementById('cropApply').addEventListener('click',async()=>{
  const id=document.querySelector('[name="id"]').value;if(!id){closeCropModal();return}
  await flushPositions();
  await apiFetch(api('update'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,crop_x:Math.round(cropData.x/CROP_RATIO),crop_y:Math.round(cropData.y/CROP_RATIO),crop_zoom:cropData.z})});
  closeCropModal();await load();
});
document.getElementById('form').addEventListener('submit',async function(e){
  e.preventDefault();
  const phones=[...document.querySelectorAll('[name="phone"]')].map(i=>i.value).filter(Boolean).join('|');
  const emails=[...document.querySelectorAll('[name="email"]')].map(i=>i.value).filter(Boolean).join('|');
  const idField=document.querySelector('[name="id"]');
  const idVal=(idField.value||'').trim();
  const data={
    full_name:document.querySelector('[name="full_name"]').value,
    position:document.querySelector('[name="position"]').value,
    phone:phones,email:emails,
    ext:(document.querySelector('[name="ext"]').value||'').trim().substring(0,6),
    birthday:(function(){
      let v=(document.querySelector('[name="birthday"]').value||'').trim();
      if(/^\d{8}$/.test(v)) return v.substring(0,2)+'.'+v.substring(2,4)+'.'+v.substring(4,8);
      if(/^\d{7}$/.test(v)) return '0'+v.substring(0,1)+'.'+v.substring(1,3)+'.'+v.substring(3,7);
      return v;
    })(),
    parent_id:parseInt(document.querySelector('[name="parent_id"]').value)||0,
    office_id:parseInt(document.querySelector('[name="office_id"]').value)||0,
    cabinet:(document.querySelector('[name="cabinet"]').value||'').substring(0,6),
    links:serializeLinks(),
    show_contacts:document.querySelector('[name="show_contacts"]').checked?1:0,
    show_office:document.querySelector('[name="show_office"]').checked?1:0,
    show_manager:document.querySelector('[name="show_manager"]').checked?1:0,
    show_in_list:document.querySelector('[name="show_in_list"]').checked?1:0,
    show_photo:document.querySelector('[name="show_photo"]').checked?1:0,
    is_department:document.querySelector('[name="is_department"]').checked?1:0,
    department:document.querySelector('[name="department"]').value||null
  };
  if(idVal!==''){
    data.id=idVal;
    const ex=employees.find(v=>v.id==idVal);
    if(ex){data.position_x=ex.position_x;data.position_y=ex.position_y;data.width=ex.width;data.height=ex.height;data.crop_x=ex.crop_x;data.crop_y=ex.crop_y;data.crop_zoom=ex.crop_zoom}
  }else{
    const cx=(canvas.offsetWidth/2-panX)/scale,cy=(canvas.offsetHeight/2-panY)/scale;
    data.position_x=Math.round(Math.max(0,cx-110));data.position_y=Math.round(Math.max(0,cy-50));data.width=220;data.height=100;
  }
  const file=document.querySelector('[name="photo"]').files[0];
  if(file){const fd=new FormData();fd.append('photo',file);try{const r=await apiFetch(api('upload'),{method:'POST',body:fd});const d=await r.json();if(d.success)data.photo=d.filepath}catch(err){console.error(err)}
    data.crop_x=0;data.crop_y=0;data.crop_zoom=1;
  }
  try{
    if(idVal!==''){await flushPositions()}
    const url=idVal!==''?api('update'):api('create');
    const res=await apiFetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    const result=await res.json();
    if(result.success){closeModal();await load()}
    else alert('Ошибка: '+(result.error||'Неизвестная'));
  }catch(err){alert('Ошибка: '+err.message)}
});
async function deleteEmployee(id){if(!id)return;if(!confirm('Точно хотите удалить?'))return;await flushPositions();await apiFetch(api('delete'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});closeModal();await load()}

/* ===== ЛИНИИ ===== */
const SVGNS='http://www.w3.org/2000/svg';
let _lineSel=null; // id выбранного ребёнка
const _lineDrag={active:false,type:null,childId:null,startVal:0,startClient:0};
function _boundsFor(p,c){
  const pc=cardEl(p.id),cc=cardEl(c.id);
  const pw=pc?pc.offsetWidth:(p.width||220),ph=pc?pc.offsetHeight:(p.height||100);
  const cw=cc?cc.offsetWidth:(c.width||220),ch=cc?cc.offsetHeight:(c.height||100);
  const px=pc?parseFloat(pc.style.left):p.position_x,py=pc?parseFloat(pc.style.top):p.position_y;
  const cx=cc?parseFloat(cc.style.left):c.position_x,cy=cc?parseFloat(cc.style.top):c.position_y;
  return {px,py,pw,ph,cx,cy,cw,ch};
}
function _computePath(p,c){
  const b=_boundsFor(p,c);
  const x1=b.px+b.pw/2, y1=b.py+b.ph;
  const x2=b.cx+b.cw/2, y2=b.cy;
  const natural=(y1+y2)/2;
  const lo=(c.line_offsets&&typeof c.line_offsets==='object')?c.line_offsets:null;
  const midY=natural+(lo&&typeof lo.midY==='number'?lo.midY:0);
  const upperX=(lo&&typeof lo.upperX==='number')?lo.upperX:0;
  const lowerX=(lo&&typeof lo.lowerX==='number')?lo.lowerX:0;
  const ux=x1+upperX;
  const lx=x2+lowerX;
  return {x1,y1,x2,y2,midY,natural,upperX,lowerX,ux,lx};
}
function drawLines(){
  if(!svg)return;
  svg.querySelectorAll('path,.line-handle').forEach(p=>p.remove());
  employees.forEach(emp=>{
    if(!emp.parent_id)return;
    const p=employees.find(x=>x.id===emp.parent_id);
    if(!p)return;
    drawLine(p,emp);
  });
  _renderHandles();
}
function drawLine(p,c){
  const r=_computePath(p,c);
  const OFFSET=12;
  const hasUpper=r.upperX!==0;
  const hasLower=r.lowerX!==0;
  const hasAny=hasUpper||hasLower;

  let d;
  if(!hasAny){
    d=`M ${r.x1} ${r.y1} L ${r.x1} ${r.midY} L ${r.x2} ${r.midY} L ${r.x2} ${r.y2}`;
  }else{
    // ВСЕГДА строим Г с обеих сторон
    let segs=[];
    segs.push(`M ${r.x1} ${r.y1}`);
    // верх
    segs.push(`L ${r.x1} ${r.y1+OFFSET}`);
    segs.push(`L ${r.ux} ${r.y1+OFFSET}`);
    segs.push(`L ${r.ux} ${r.midY}`);
    // низ
    segs.push(`L ${r.lx} ${r.midY}`);
    segs.push(`L ${r.lx} ${r.y2-OFFSET}`);
    segs.push(`L ${r.x2} ${r.y2-OFFSET}`);
    segs.push(`L ${r.x2} ${r.y2}`);
    d=segs.join(' ');
  }

  // hit
  const hit=document.createElementNS(SVGNS,'path');
  hit.setAttribute('d',d);
  hit.setAttribute('class','line-hit');
  hit.setAttribute('data-line-for',c.id);
  hit.setAttribute('fill','none');
  hit.setAttribute('stroke','transparent');
  hit.setAttribute('stroke-width','20');
  hit.style.pointerEvents='stroke';
  svg.appendChild(hit);

  const vis=document.createElementNS(SVGNS,'path');
  vis.setAttribute('d',d);
  vis.setAttribute('class','line-visible'+(_lineSel===c.id?' selected':''));
  vis.setAttribute('data-line-for',c.id);
  vis.setAttribute('fill','none');
  vis.style.pointerEvents='none';
  svg.appendChild(vis);

  hit.addEventListener('mousedown',function(ev){
    if(ev.button!==0)return;
    ev.stopPropagation();ev.preventDefault();
    _lineSel=c.id;
    svg.querySelectorAll('.line-visible').forEach(el=>el.classList.remove('selected'));
    vis.classList.add('selected');
const pt=toLocal(ev);
const midThreshold=15; // радиус захвата горизонтали
const vertThreshold=15; // радиус захвата вертикали
const minX=Math.min(r.ux,r.lx), maxX=Math.max(r.ux,r.lx);
const inMidX = pt.x >= minX-midThreshold && pt.x <= maxX+midThreshold;
const inMidY = Math.abs(pt.y-r.midY) <= midThreshold;

// 1) Если в пределах горизонтали (X между ux и lx, Y близко к midY) — midY
if(inMidX && inMidY){
  _lineDrag.type='midY';_lineDrag.startVal=r.midY;
}
// 2) Иначе — вертикаль: смотрим, ближе к ux или lx
else{
  const dUp=Math.abs(pt.x-r.ux);
  const dLow=Math.abs(pt.x-r.lx);
  if(dUp<=dLow){_lineDrag.type='upperX';_lineDrag.startVal=r.upperX;}
  else{_lineDrag.type='lowerX';_lineDrag.startVal=r.lowerX;}
}
    _lineDrag.active=true;_lineDrag.childId=c.id;
    _lineDrag.startClient={x:ev.clientX,y:ev.clientY};
  });
  hit.addEventListener('dblclick',async function(ev){
    ev.stopPropagation();ev.preventDefault();
    await _saveLineOffsets(c.id,null);
  });
}
function _renderHandles(){
  // ручки больше не рисуем — тянем прямо за линию
  return;
}
function _onLineDragMove(ev){
  if(!_lineDrag.active||_lineDrag.childId===null)return;
  const c=employees.find(x=>x.id===_lineDrag.childId);if(!c)return;
  const p=employees.find(x=>x.id===c.parent_id);if(!p)return;
  if(!c.line_offsets||typeof c.line_offsets!=='object')c.line_offsets={};
  const r=_computePath(p,c);
  if(_lineDrag.type==='midY'){
    const dy=(ev.clientY-_lineDrag.startClient.y)/scale;
    const natural=(r.y1+r.y2)/2;
    let newMidY=_lineDrag.startVal+dy;
    const minY=r.y1+16, maxY=r.y2-16;
    if(maxY<minY){newMidY=(r.y1+r.y2)/2;}
    else{if(newMidY<minY)newMidY=minY;if(newMidY>maxY)newMidY=maxY;}
    c.line_offsets.midY=Math.round(newMidY-natural);
  }else if(_lineDrag.type==='upperX'){
    const dx=(ev.clientX-_lineDrag.startClient.x)/scale;
    c.line_offsets.upperX=Math.round(_lineDrag.startVal+dx);
    if(Math.abs(c.line_offsets.upperX)<3)c.line_offsets.upperX=0;
  }else if(_lineDrag.type==='lowerX'){
    const dx=(ev.clientX-_lineDrag.startClient.x)/scale;
    c.line_offsets.lowerX=Math.round(_lineDrag.startVal+dx);
    if(Math.abs(c.line_offsets.lowerX)<3)c.line_offsets.lowerX=0;
  }
  svg.querySelectorAll(`path[data-line-for="${c.id}"]`).forEach(el=>el.remove());
  drawLine(p,c);
}
async function _endLineDrag(){
  if(!_lineDrag.active)return;
  const cid=_lineDrag.childId;
  _lineDrag.active=false;_lineDrag.type=null;_lineDrag.childId=null;
  if(cid===null)return;
  const c=employees.find(x=>x.id===cid);if(!c)return;
  await _saveLineOffsets(cid,c.line_offsets||null);
}
async function _saveLineOffsets(id,lo){
  const c=employees.find(x=>x.id===id);if(!c)return;
  if(lo===null){c.line_offsets=null;}
  else{
    const clean={};
    if(typeof lo.midY==='number')clean.midY=lo.midY;
    if(typeof lo.upperX==='number' && lo.upperX!==0)clean.upperX=lo.upperX;
    if(typeof lo.lowerX==='number' && lo.lowerX!==0)clean.lowerX=lo.lowerX;
    c.line_offsets = Object.keys(clean).length ? clean : null;
    lo = c.line_offsets;
  }
  try{
    await apiFetch(api('update'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id,line_offsets:lo})});
  }catch(e){console.error('saveLineOffsets',e);}
  drawLines();
}
// Снять выделение с линии по клику в пустоту
canvas.addEventListener('mousedown',function(e){
  if(e.target===canvas||e.target===container){
    if(_lineSel!==null){_lineSel=null;drawLines();}
  }
},true);

/* ===== ПРОЧЕЕ ===== */
function updateParentSelect(excludeId){const s=document.querySelector('[name="parent_id"]');s.innerHTML='<option value="0">Нет</option>';employees.forEach(e=>{if(excludeId&&e.id===excludeId)return;const o=document.createElement('option');o.value=e.id;o.textContent=e.full_name;s.appendChild(o)})}
function zoom(f){scale*=f;scale=Math.max(0.2,Math.min(3,scale));updateTransform()}
const resetZoom=()=>{scale=1;panX=0;panY=0;updateTransform()};
function viewResult(){window.location.href='/dashboard?project='+PROJECT_ID}
window.addEventListener('resize',drawLines);
window.addEventListener('click',e=>{
  if(e.target===document.getElementById('modal'))closeModal();
  if(e.target===document.getElementById('cropModal'))closeCropModal();
  if(e.target===document.getElementById('officeModal'))closeOfficeModal();
  if(e.target===document.getElementById('linkModal'))closeLinkModal();
});
canvas.addEventListener('wheel',e=>{if(!e.ctrlKey)return;e.preventDefault();const r=canvas.getBoundingClientRect(),mx=e.clientX-r.left,my=e.clientY-r.top;const ns=Math.max(0.2,Math.min(3,scale*(e.deltaY<0?1.1:1/1.1)));panX=mx-(mx-panX)*(ns/scale);panY=my-(my-panY)*(ns/scale);scale=ns;updateTransform()},{passive:false});
async function renameProject(){
  const name=prompt('Новое название проекта:',window.PROJECT_NAME||'');
  if(!name)return;
  const r=await apiFetch('api.php?action=project_rename',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:PROJECT_ID,name})});
  const d=await r.json();
  if(d.success){window.PROJECT_NAME=name;document.getElementById('projectTitle').textContent=name;}
  else alert('Ошибка: '+(d.error||'неизвестная'));
}
async function shareProject(){
  const r=await apiFetch('api.php?action=project_share',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:PROJECT_ID})});
  const d=await r.json();
  if(!d.success){alert('Ошибка: '+(d.error||'неизвестная'));return}
  document.getElementById('shareUrl').value=d.url;
  document.getElementById('shareModal').classList.add('show-flex');
}
function copyShareUrl(){const inp=document.getElementById('shareUrl');inp.select();inp.setSelectionRange(0,99999);navigator.clipboard.writeText(inp.value).then(()=>alert('Скопировано!'))}
async function unshareProject(){
  if(!confirm('Отключить публичный доступ?'))return;
  await apiFetch('api.php?action=project_unshare',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:PROJECT_ID})});
  document.getElementById('shareModal').classList.remove('show-flex');alert('Публичный доступ отключён');
}
window.addEventListener('beforeunload',()=>{
  const pos=employees.map(e=>{const c=cardEl(e.id);
    return{id:e.id,position_x:c?Math.round(parseFloat(c.style.left)||0):e.position_x,position_y:c?Math.round(parseFloat(c.style.top)||0):e.position_y,width:e.width,height:e.height,parent_id:e.parent_id};
  });
  const data=JSON.stringify({employees:pos,csrf_token:CSRF_TOKEN});
  navigator.sendBeacon('api.php?action=save&project='+PROJECT_ID,new Blob([data],{type:'application/json'}));
});
function formatDateRu(iso){
  if(!iso)return '';
  const p=String(iso).split('-');
  if(p.length!==3)return iso;
  return p[2]+'.'+p[1]+'.'+p[0];
}
document.addEventListener('input',function(e){
  if(!e.target||e.target.name!=='birthday')return;
  let v=e.target.value.replace(/\D/g,'').substring(0,8);
  if(!v){e.target.value='';return;}
  let out='';
  const dayStr=v.substring(0,2);
  const monthStr=v.substring(2,4);
  const yearStr=v.substring(4,8);
  if(dayStr.length===1 && v.length>1) out+='0'+dayStr; else out+=dayStr;
  if(monthStr.length>0){
    if(monthStr.length===1 && v.length>3) out+='.0'+monthStr; else out+='.'+monthStr;
  }
  if(yearStr.length>0) out+='.'+yearStr;
  e.target.value=out;
});
/* ===== СПИСОК СОТРУДНИКОВ (drag-and-drop) ===== */
let _listSort='default';
let _listOrderCache=null;

function _getVisibleEmployees(){
  return employees.filter(e=>(e.show_in_list==0)?false:true);
}
function _sortedByAlpha(list){
  return list.slice().sort((a,b)=>{
    return (a.full_name||'').toLowerCase().localeCompare((b.full_name||'').toLowerCase(),'ru');
  });
}
function _sortedByDefault(list){
  // по list_order, затем id
  return list.slice().sort((a,b)=>{
    const ao=(a.list_order||0),bo=(b.list_order||0);
    if(ao!==bo)return ao-bo;
    return a.id-b.id;
  });
}
function openListModal(){
  _listOrderCache=null;
  document.getElementById('listModal').classList.add('show-flex');
  _listSort='default';
  document.querySelectorAll('.list-filter-btn').forEach(b=>b.classList.toggle('active',b.dataset.sort==='default'));
  _renderListModal();
}
function closeListModal(){document.getElementById('listModal').classList.remove('show-flex');}
function setListSort(sort,btn){
  _listSort=sort;
  document.querySelectorAll('.list-filter-btn').forEach(b=>b.classList.toggle('active',b===btn));
  _renderListModal();
}
function _renderListModal(){
  const body=document.getElementById('listBody');
  let list=_getVisibleEmployees();
  if(_listSort==='alpha'){list=_sortedByAlpha(list);}
  else{list=_sortedByDefault(list);}
  if(!list.length){body.innerHTML='<div style="text-align:center;color:var(--slate);padding:20px;font-size:13px">Нет сотрудников для отображения</div>';return;}
  body.innerHTML=list.map(e=>`<div class="list-item" draggable="true" data-id="${e.id}"><span class="drag-handle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="18" r="1"/></svg></span><span>${esc(e.full_name)}</span></div>`).join('');
  _bindListDrag();
}
function _bindListDrag(){
  const items=document.querySelectorAll('#listBody .list-item');
  let dragEl=null;
  items.forEach(el=>{
    el.addEventListener('dragstart',function(e){
      dragEl=el;el.classList.add('dragging');
      e.dataTransfer.effectAllowed='move';
      try{e.dataTransfer.setData('text/plain',el.dataset.id);}catch(_){}
    });
    el.addEventListener('dragend',function(){el.classList.remove('dragging');items.forEach(i=>i.classList.remove('drag-over'));dragEl=null;});
    el.addEventListener('dragover',function(e){
      e.preventDefault();e.dataTransfer.dropEffect='move';
      if(!dragEl||dragEl===el)return;
      items.forEach(i=>i.classList.remove('drag-over'));
      el.classList.add('drag-over');
    });
    el.addEventListener('dragleave',function(){el.classList.remove('drag-over');});
    el.addEventListener('drop',function(e){
      e.preventDefault();
      el.classList.remove('drag-over');
      if(!dragEl||dragEl===el)return;
      const parent=el.parentNode;
      // определить, вставлять до или после
      const rect=el.getBoundingClientRect();
      const after=(e.clientY>rect.top+rect.height/2);
      if(after){parent.insertBefore(dragEl,el.nextSibling);}
      else{parent.insertBefore(dragEl,el);}
    });
  });
}
async function saveListOrder(){
const items=document.querySelectorAll('#listBody .list-item');
const order=[];
items.forEach(el=>order.push(parseInt(el.dataset.id)));
// добавим невидимых (show_in_list=0) в конец, чтобы list_order стал уникальным
employees.forEach(e=>{
  if(e.show_in_list!=0)return;
  if(order.indexOf(e.id)===-1)order.push(e.id);
});
  try{
    const r=await apiFetch(api('list_reorder'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({order})});
    const d=await r.json();
    console.log('Save response:',d);
    if(d.success){
      order.forEach((id,i)=>{const e=employees.find(v=>v.id===id);if(e)e.list_order=i;});
      alert('Порядок сохранён');
      closeListModal();
    }else{alert('Ошибка: '+(d.error||'неизвестная'));}
  }catch(e){alert('Ошибка сети: '+e.message);}
}