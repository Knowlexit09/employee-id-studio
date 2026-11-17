/* ID Studio canvas editor. No remote scripts, no eval, no user-authored HTML. */
import {moveRect, resizeRect, alignRect, reorderLayers, findAlignmentGuides} from './id-studio-geometry.js';
const root = document.getElementById('studio-designer');
if (root) {
  const config = JSON.parse(document.getElementById('id-studio-config').textContent);
  const $ = id => document.getElementById(id);
  const layout = structuredClone(config.layout);
  const assets = [...config.assets];
  const card = {width: config.cardWidth, height: config.cardHeight};
  const preview = {
    full_name:'JUAN DELA CRUZ', first_name:'JUAN', last_name:'DELA CRUZ',
    employee_code:'EMP-0001', job_title:'Software Engineer',
    department:'Technology', branch:'Head Office', email:'employee@example.test',
    company_name:'YOUR COMPANY', issue_date:'2026-11-16', expiry_date:'2028-11-16',
  };
  const canvas = $('id-canvas');
  let side = 'front', selectedId = null, revision = config.revision, dirty = false, drag = null, previewMode = false;
  const scale = Math.min(620 / card.width, 355 / card.height, 7.2);
  const getElements = () => layout[side].elements;
  const current = () => getElements().find(e => e.id === selectedId);
  const assetUrl = (id, kind) => assets.find(a => a.id === id && a.kind === kind)?.url ?? null;
  const clamp = (v,min,max) => Math.min(max,Math.max(min,v));
  const round = n => Math.round(n*100)/100;
  const markDirty = () => {dirty = true; $('designer-status').textContent = 'Unsaved changes.';};
  const fontPx = e => `${e.fontSize * 25.4 / 72 * scale}px`;
  const allowedBindings = {
    text: [ ['custom','Custom text'], ['full_name','Full name'], ['first_name','First name'],
      ['last_name','Last name'], ['employee_code','Employee code'], ['job_title','Job title'],
      ['department','Department'], ['branch','Branch'], ['email','Work email'],
      ['company_name','Company name'], ['issue_date','Issue date'], ['expiry_date','Expiry date'] ],
    image: [['photo','Employee photo'], ['logo','Company logo']],
    qr: [['verification_url','QR verification URL']],
  };
  const labelFor = e => e.type==='text' ? (e.binding==='custom' ? (e.text || 'Custom text') : e.binding.replace(/_/g,' ')) : e.type==='image' ? e.binding : 'QR code';

  function applyGeometry(node, e) {
    Object.assign(node.style, {
      left: `${e.x*scale}px`, top: `${e.y*scale}px`, width:`${e.w*scale}px`,height:`${e.h*scale}px`,
      zIndex: String(e.z),fontFamily:e.fontFamily,fontSize:fontPx(e),
      fontWeight:e.bold?'700':'400',fontStyle:e.italic?'italic':'normal',
      color:e.color,justifyContent:e.align==='left'?'flex-start':e.align==='right'?'flex-end':'center',
      textAlign:e.align,
    });
  }

  function render() {
    canvas.replaceChildren();
    canvas.classList.toggle('previewing', previewMode);
    $('toggle-preview').textContent = previewMode ? 'Exit preview' : 'Preview design';
    $('toggle-preview').setAttribute('aria-pressed', String(previewMode));
    for (const addButton of root.querySelectorAll('[data-add]')) addButton.disabled = previewMode;
    $('upload-asset').disabled = previewMode;
    $('background-id').disabled = previewMode;
    $('logo-id').disabled = previewMode;
    canvas.style.width=`${card.width*scale}px`;
    canvas.style.height=`${card.height*scale}px`;
    const bg=assetUrl(layout[side].background_asset_id,'background');
    canvas.style.backgroundImage=bg?`url("${bg}")`: 'none';
    canvas.style.backgroundSize='100% 100%';
    for (const e of getElements().slice().sort((a,b)=>a.z-b.z)) {
      const node=document.createElement('div');
      node.className='studio-element'+(e.id===selectedId?' selected':'')+(e.locked?' locked':'');
      node.dataset.id=e.id;
      applyGeometry(node,e);
      if(e.type==='text') node.textContent=e.binding==='custom'?e.text:(preview[e.binding] ?? '');
      else if(e.type==='qr') { node.textContent='▦ QR PREVIEW'; node.style.background='#f8fafc';node.style.border='1px dashed #94a3b8';node.style.fontWeight='700'; }
      else if(e.type==='image') {
        const src=e.binding==='logo'?assetUrl(layout[side].logo_asset_id,'logo'):null;
        if(src){const img=document.createElement('img');img.src=src;img.alt='Logo preview';Object.assign(img.style,{width:'100%',height:'100%',objectFit:'contain'});node.append(img);}
        else {node.textContent=e.binding==='photo'?'EMPLOYEE PHOTO':'LOGO';node.style.background='#eef2ff';node.style.color='#64748b';node.style.border='1px dashed #94a3b8';}
      }
      if(e.id===selectedId && !e.locked) {
        const handle=document.createElement('span');handle.className='studio-handle';handle.dataset.resize='true';node.append(handle);
      }
      canvas.append(node);
    }
    const v = document.createElement('div');
    v.className = 'alignment-guide alignment-guide-v';
    v.setAttribute('aria-hidden', 'true');
    const h = document.createElement('div');
    h.className = 'alignment-guide alignment-guide-h';
    h.setAttribute('aria-hidden', 'true');
    canvas.append(v, h);
    renderLayers();renderPanel();renderAssets();
    root.querySelectorAll('[data-side]').forEach(button=>{
      const active=button.dataset.side===side;
      button.style.background=active?'#4338ca':'white';button.style.color=active?'white':'#0f172a';
      button.setAttribute('aria-pressed',String(active));
    });
  }

  function renderLayers() {
    const host=$('layers');host.replaceChildren();
    const all=getElements().slice().sort((a,b)=>b.z-a.z);
    $('layer-count').textContent=`(${all.length})`;
    for (const e of all) {
      const button=document.createElement('button');button.type='button';
      button.className='layer-item'+(e.id===selectedId?' active':'');
      const name=document.createElement('span');name.textContent=`${e.locked?'🔒 ':''}${labelFor(e)}`;
      button.title=e.id;button.append(name);
      button.addEventListener('click',()=>{selectedId=e.id;render();canvas.focus({preventScroll:true});});host.append(button);
    }
    for (const id of ['duplicate-element','delete-element','lock-element']) $(id).disabled = previewMode || !current();
    for (const id of ['layer-up','layer-down','layer-front','layer-back']) {
      $(id).disabled = previewMode || !current() || current().locked;
    }
    for (const button of root.querySelectorAll('[data-align]')) button.disabled = previewMode || !current() || current().locked;
    $('lock-element').textContent=current()?.locked?'Unlock':'Lock';
  }

  function renderPanel() {
    const e=current();$('property-panel').hidden=!e;$('no-selection').hidden=!!e;
    if(!e)return;
    const bind=$('prop-binding');bind.replaceChildren();
    for (const [value,label] of allowedBindings[e.type]) bind.add(new Option(label,value));
    bind.value=e.binding;
    $('custom-text-label').hidden=!(e.type==='text'&&e.binding==='custom');
    $('prop-text').value=e.text ?? '';
    for(const key of ['x','y','w','h']) $('prop-'+key).value=e[key];
    $('font-properties').hidden=e.type!=='text';
    for (const key of ['fontSize','fontFamily','color','align']) $('prop-'+key).value=e[key];
    $('prop-bold').checked=e.bold;$('prop-italic').checked=e.italic;
  }

  function renderAssets() {
    for (const [key,kind] of [['background','background'],['logo','logo']]) {
      const select=$(key+'-id');const previous=String(layout[side][key+'_asset_id'] ?? '');
      select.replaceChildren(new Option('None',''));
      for (const a of assets.filter(a=>a.kind===kind)) select.add(new Option(`${kind} #${a.id}`,String(a.id)));
      select.value=previous;
    }
  }

  function showGuides(vertical = null, horizontal = null) {
    const v = canvas.querySelector('.alignment-guide-v');
    const h = canvas.querySelector('.alignment-guide-h');
    if (!v || !h) return;
    v.hidden = vertical === null;
    h.hidden = horizontal === null;
    if (vertical !== null) v.style.left = (vertical * scale)+'px';
    if (horizontal !== null) h.style.top = (horizontal * scale)+'px';
  }

  canvas.addEventListener('pointerdown',ev=>{
    if (previewMode) return;
    const node=ev.target.closest('.studio-element');
    if(!node){selectedId=null;render();return;}
    const e=getElements().find(item=>item.id===node.dataset.id);
    if(!e)return;
    selectedId=e.id;
    canvas.focus({preventScroll:true});
    const mode=ev.target.matches('[data-resize]')?'resize':'move';
    if(!e.locked){drag={id:e.id,mode,startX:ev.clientX,startY:ev.clientY,x:e.x,y:e.y,w:e.w,h:e.h};canvas.setPointerCapture(ev.pointerId);}
    render();
  });
  canvas.addEventListener('pointermove',ev=>{
    if(!drag)return;
    const e=getElements().find(item=>item.id===drag.id);
    if(!e)return;
    const dx=(ev.clientX-drag.startX)/scale,dy=(ev.clientY-drag.startY)/scale;
    if(drag.mode==='move'){
      let next=moveRect(drag, dx, dy, card.width, card.height, $('snap-grid').checked ? 0.5 : 0);
      if($('smart-guides').checked){
        const targets=getElements().filter(item=>item.id!==e.id);
        const aligned=findAlignmentGuides({...e,...next},targets,card.width,card.height);
        next={x:aligned.x,y:aligned.y};
        showGuides(aligned.vertical, aligned.horizontal);
      } else showGuides();
      e.x=next.x;
      e.y=next.y;
    }else{
      const next=resizeRect(drag, dx, dy, card.width, card.height, $('snap-grid').checked ? 0.5 : 0);
      e.w=next.w;
      e.h=next.h;
      showGuides();
    }
    const node=Array.from(canvas.children).find(el=>el.dataset.id===e.id);
    if(node)applyGeometry(node,e);
    for(const key of ['x','y','w','h'])$('prop-'+key).value=e[key];
    markDirty();
  });
  function finishDrag(){showGuides();if(drag){drag=null;render();}}
  canvas.addEventListener('pointerup',finishDrag);canvas.addEventListener('pointercancel',finishDrag);

  $('toggle-preview').addEventListener('click',()=>{previewMode=!previewMode;selectedId=previewMode?null:selectedId;showGuides();render();});
  root.querySelectorAll('[data-align]').forEach(button=>button.addEventListener('click',()=>{
    const e=current();if(!e || e.locked)return;
    Object.assign(e, alignRect(e, button.dataset.align, card.width, card.height));
    markDirty();render();
  }));
  for(const [buttonId,command] of [['layer-up','up'],['layer-down','down'],['layer-front','front'],['layer-back','back']]){
    $(buttonId).addEventListener('click',()=>{
      const e=current();if(!e || e.locked)return;
      layout[side].elements=reorderLayers(getElements(),e.id,command);
      markDirty();render();
    });
  }
  root.addEventListener('keydown',ev=>{
    if(previewMode || ev.altKey || ev.ctrlKey || ev.metaKey || !['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(ev.key))return;
    if(!canvas.contains(ev.target) && !ev.target.closest('.layer-item'))return;
    const e=current();if(!e || e.locked)return;
    const step=ev.shiftKey?1:0.1;
    const dx=ev.key==='ArrowLeft'?-step:ev.key==='ArrowRight'?step:0;
    const dy=ev.key==='ArrowUp'?-step:ev.key==='ArrowDown'?step:0;
    const next=moveRect(e,dx,dy,card.width,card.height,0);
    ev.preventDefault();
    if(next.x!==e.x || next.y!==e.y){
      e.x=next.x;e.y=next.y;markDirty();render();
    }
  });

  root.querySelectorAll('[data-side]').forEach(b=>b.addEventListener('click',()=>{side=b.dataset.side;selectedId=null;render();}));
  root.querySelectorAll('[data-add]').forEach(b=>b.addEventListener('click',()=>{
    if(previewMode)return;
    const type=b.dataset.add;
    const binding=type==='image'?'photo':type==='qr'?'verification_url':'custom';
    const width=type==='text'?Math.min(40,card.width-8):Math.min(20,card.width-8);
    const height=type==='text'?8:Math.min(20,card.height-8);
    const z=Math.max(0,...getElements().map(e=>e.z))+1;
    const id='f_'+crypto.randomUUID().replaceAll('-','').slice(0,20);
    getElements().push({id,type,binding,text:type==='text'?'New text':'',x:4,y:4,w:width,h:height,fontSize:12,fontFamily:'Arial',bold:false,italic:false,color:'#0f172a',align:'left',locked:false,z});
    selectedId=id;markDirty();render();
  }));
  $('prop-binding').addEventListener('change',ev=>{const e=current();if(!e)return;e.binding=ev.target.value;markDirty();render();});
  $('prop-text').addEventListener('input',ev=>{const e=current();if(e){e.text=ev.target.value;markDirty();const node=Array.from(canvas.children).find(n=>n.dataset.id===e.id);if(node&&e.binding==='custom'){const first=node.firstChild;if(first&&first.nodeType===Node.TEXT_NODE)first.textContent=e.text;else node.insertBefore(document.createTextNode(e.text),first);}}});
  root.querySelectorAll('[data-number]').forEach(input=>input.addEventListener('change',()=>{
    const e=current();if(!e)return;
    const v=Number(input.value);if(!Number.isFinite(v))return render();
    const key=input.dataset.number;
    e[key]=key==='x'?clamp(v,0,card.width-e.w):key==='y'?clamp(v,0,card.height-e.h):key==='w'?clamp(v,2,card.width-e.x):clamp(v,2,card.height-e.y);
    e[key]=round(e[key]);markDirty();render();
  }));
  for(const key of ['fontSize','fontFamily','color','align']) $('prop-'+key).addEventListener('change',ev=>{
    const e=current();if(!e)return;
    e[key]=key==='fontSize'?clamp(Number(ev.target.value)||11,5,42):ev.target.value;
    markDirty();render();
  });
  for(const key of ['bold','italic']) $('prop-'+key).addEventListener('change',ev=>{const e=current();if(e){e[key]=ev.target.checked;markDirty();render();}});
  $('delete-element').addEventListener('click',()=>{if(!current())return;layout[side].elements=getElements().filter(e=>e.id!==selectedId);selectedId=null;markDirty();render();});
  $('duplicate-element').addEventListener('click',()=>{const e=current();if(!e)return;const dup=structuredClone(e);dup.id='f_'+crypto.randomUUID().replaceAll('-','').slice(0,20);dup.x=clamp(e.x+2,0,card.width-e.w);dup.y=clamp(e.y+2,0,card.height-e.h);dup.locked=false;dup.z=Math.max(0,...getElements().map(x=>x.z))+1;getElements().push(dup);selectedId=dup.id;markDirty();render();});
  $('lock-element').addEventListener('click',()=>{const e=current();if(e){e.locked=!e.locked;markDirty();render();}});
  for(const key of ['background','logo']) $(key+'-id').addEventListener('change',ev=>{layout[side][key+'_asset_id']=ev.target.value?Number(ev.target.value):null;markDirty();render();});
  $('upload-asset').addEventListener('click',async()=>{
    const file=$('asset-file').files[0];if(!file){$('designer-status').textContent='Choose a JPEG, PNG or WebP image.';return;}
    const kind=$('asset-kind').value,fd=new FormData();fd.append('file',file);fd.append('kind',kind);
    $('designer-status').textContent='Uploading image…';
    try{
      const res=await fetch(config.uploadUrl,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'},body:fd,credentials:'same-origin'});
      if(!res.ok)throw new Error(`Upload rejected (${res.status}). Check file type and size.`);
      const a=await res.json();assets.push(a);layout[side][kind+'_asset_id']=a.id;
      $('asset-file').value='';markDirty();render();
    }catch(err){$('designer-status').textContent=err.message;}
  });
  async function postJSON(url,method,payload){
    const res=await fetch(url,{method,credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(payload)});
    if(!res.ok){const error=await res.json().catch(()=>({}));throw new Error(error.message||`Request failed (${res.status})`);}
    return res.json();
  }
  $('save-template').addEventListener('click',async()=>{
    $('designer-status').textContent='Saving draft…';
    try{const data=await postJSON(config.saveUrl,'PUT',{revision,layout});revision=data.revision;dirty=false;$('revision-label').textContent=revision;$('designer-status').textContent='Draft saved. Publish when ready.';}
    catch(err){$('designer-status').textContent=err.message+' If stale, reload this page.';}
  });
  $('publish-template').addEventListener('click',async()=>{
    if(dirty){$('designer-status').textContent='Save the draft before requesting approval.';return;}
    if(!confirm('Submit this saved draft to a different manager or administrator for approval?'))return;
    try{const data=await postJSON(config.publishUrl,'POST',{revision});$('designer-status').textContent=`Revision ${data.revision} submitted for independent approval. A manager or another administrator must approve it before use.`;}
    catch(err){$('designer-status').textContent=err.message;}
  });
  window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
  render();
}
