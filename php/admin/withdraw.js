document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.withdraw-btn').forEach(b=>{
    b.addEventListener('click',async e=>{
      const addr=b.dataset.address, key=b.dataset.privkey; const amt=prompt('Снять сколько с '+addr+' (Tron):'); if(!amt)return;
      try{const res=await fetch('withdraw.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({address:addr,privkey:key,amount:parseFloat(amt)})});const r=await res.json();alert(r.message||r.error);}
      catch(err){alert(err.message||err);}
    });
  });
  const all=document.getElementById('sweep-all');
  if(all) all.addEventListener('click',async e=>{
    if(!confirm('Снять со всех адресов на мастер?')) return;
    all.disabled=true; all.textContent='Идёт sweep...';
    try{const res=await fetch('sweep.php',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});const r=await res.json(); alert((r.message||r.error||'done')+'\n'+(r.detail||'')); }
    catch(err){alert(err.message||err);} finally {all.disabled=false; all.textContent='Sweep all to master';}
  });
});
