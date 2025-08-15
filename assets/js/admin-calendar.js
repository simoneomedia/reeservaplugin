
(function(){
  const ajax = RSV_ADMIN.ajax,
        nonceBooked = RSV_ADMIN.nonceBooked,
        nonceLoad   = RSV_ADMIN.nonceLoad,
        nonceDay    = RSV_ADMIN.nonceDay,
        nonceUpdate = RSV_ADMIN.nonceUpdate;
  const selectEl = document.getElementById('accommodation-select');
  const summaryBody = document.getElementById('summary-body');
  const priceModal = document.getElementById('price-modal');
  const selectedDates = document.getElementById('selected-dates');
  const warningEl = document.getElementById('price-warning');
  const baseBox = document.getElementById('base-periods');
  const varsCt  = document.getElementById('variations-container');
  const addVarBtn = document.getElementById('add-variation');
  const addPeriodBtn = document.getElementById('add-period');
  const saveBtn = document.getElementById('save-price');
  const createBtn = document.getElementById('create-admin-booking');
  const syncBtn = document.getElementById('rsv-sync-ical');
  const syncRes = document.getElementById('rsv-sync-result');

  let currentType = parseInt(selectEl ? selectEl.value : 0,10);
  let selectedRange = null;

  function fmtRange(startISO,endISO){
    const d1=new Date(startISO),d2=new Date(endISO);
    const fmt=(opts)=>new Intl.DateTimeFormat('en-GB',opts);
    const cap=s=>s.charAt(0).toUpperCase()+s.slice(1);
    return startISO===endISO ? cap(fmt({weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(d1))
      :'from '+cap(fmt({weekday:'long',day:'numeric',month:'long'}).format(d1))+' to '+cap(fmt({weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(d2));
  }

  function fetchBooked(info, success){
    const p = new URLSearchParams({ action:'rsv_get_booked', nonce:nonceBooked, type_id: currentType });
    fetch(ajax+'?'+p, {credentials:'same-origin'}).then(r=>r.json()).then(json=>{
      const data = json.data || [];
      success(data.map(e=> ({
        title: e.title, start: e.start, end: e.end, allDay:true,
        backgroundColor:'#fee2e2', borderColor:'#fee2e2', classNames:['rsv-booking']
      })));
      // summary
      summaryBody.innerHTML='';
      data.forEach(i=>{
        const tr=document.createElement('tr'); tr.innerHTML='<td>'+i.start+'</td>'; summaryBody.appendChild(tr);
      });
    });
  }
  function fetchPrices(){
    return {
      url: ajax,
      method: 'GET',
      extraParams: ()=>({ action:'rsv_load_prices', nonce: nonceLoad, type_id: currentType })
    };
  }

  const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
    initialView:'dayGridMonth', height:'auto', selectable:true, dayMaxEvents:true, eventDisplay:'block',
    eventSources: [{ events: fetchBooked }, fetchPrices() ],
    eventDidMount(info){
      if(info.event.extendedProps && info.event.extendedProps.isPrice) info.el.classList.add('price-event');
      if(info.event.classNames && info.event.classNames.indexOf('rsv-booking')>-1){
        info.el.style.borderRadius='8px';
      }
    },
    select(info){
      selectedRange = {
        start: info.startStr,
        end: new Date(new Date(info.endStr)-86400000).toISOString().split('T')[0]
      };
      selectedDates.textContent = fmtRange(selectedRange.start, selectedRange.end);
      warningEl.style.display='none'; baseBox.innerHTML=''; varsCt.innerHTML='';
      addTier(1,0); // default first tier
      // If single day, try load existing
      if(selectedRange.start===selectedRange.end){
        const qs = new URLSearchParams({action:'rsv_day_prices',nonce:nonceDay,type_id:currentType,start:selectedRange.start});
        fetch(ajax+'?'+qs,{credentials:'same-origin'}).then(r=>r.json()).then(d=>{
          if(d && d.status==='single'){
            baseBox.innerHTML='';
            (d.periods||[]).forEach((n,i)=> addTier(parseInt(n,10), (d.prices||[])[i] || 0));
            (d.variations||[]).forEach(v => addVariation(v.adults||1, v.children||0, v.prices||[]));
          } else if(d && d.status==='multiple'){
            warningEl.textContent='Different prices across selected days. Saving will overwrite them.';
            warningEl.style.display='block';
          }
        });
      }
      priceModal.classList.add('open');
    }
  });
  calendar.render();

  if(selectEl){ selectEl.addEventListener('change', ()=>{ currentType = parseInt(selectEl.value,10); calendar.refetchEvents(); }); }
  document.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', ()=> priceModal.classList.remove('open')));

  function addTier(nights, price){
    const row=document.createElement('div'); row.className='period-row';
    row.innerHTML = 'Nights <input type="number" class="tier-nights" value="'+(nights||1)+'" min="1"> ' +
                    'Price <input type="number" class="tier-price" value="'+(price||0)+'" min="0" step="1"> ' +
                    '<button class="button-link delete">×</button>';
    row.querySelector('.delete').onclick = ()=> row.remove();
    baseBox.append(row);
  }
  function addVariation(adults,children,prices){
    const card=document.createElement('div'); card.className='var-card';
    const inner = document.createElement('div'); inner.className='var-row';
    inner.innerHTML = 'Adults <input type="number" class="var-adults" value="'+(adults||1)+'" min="1"> ' +
                      'Children <input type="number" class="var-children" value="'+(children||0)+'" min="0"> ';
    card.append(inner);
    // add a price input per tier
    function refreshTierInputs(){
      let wrap = card.querySelector('.tier-prices'); if(!wrap){ wrap=document.createElement('div'); wrap.className='tier-prices'; card.append(wrap); }
      wrap.innerHTML='';
      const tiers = Array.from(baseBox.querySelectorAll('.period-row'));
      tiers.forEach((t,idx)=>{
        const nights = parseInt(t.querySelector('.tier-nights').value,10) || 1;
        const p = (prices && typeof prices[idx] !== 'undefined') ? prices[idx] : 0;
        const row=document.createElement('div'); row.className='var-row';
        row.innerHTML = 'Nights '+nights+': <input type="number" class="var-price" data-idx="'+idx+'" value="'+p+'">';
        wrap.append(row);
      });
    }
    refreshTierInputs();
    baseBox.addEventListener('input', refreshTierInputs, {once:false});
    const del = document.createElement('button'); del.textContent='Remove'; del.className='button-link'; del.onclick=()=> card.remove();
    card.append(del);
    varsCt.append(card);
  }

  if(addPeriodBtn){ addPeriodBtn.onclick = ()=> addTier(1,0); }
  if(addVarBtn){ addVarBtn.onclick = ()=> addVariation(1,0,[]); }

  if(saveBtn){
    saveBtn.onclick = ()=>{
      if(!selectedRange){ alert('Select dates on the calendar'); return; }
      const tiers = Array.from(baseBox.querySelectorAll('.period-row')).map(r=>({
        nights: parseInt(r.querySelector('.tier-nights').value,10)||1,
        price:  parseFloat(r.querySelector('.tier-price').value)||0
      }));
      if(!tiers.length){ alert('Add at least one nights tier'); return; }
      const periods = tiers.map(t=>t.nights);
      const base_prices = tiers.map(t=>t.price);
      const variations = Array.from(varsCt.querySelectorAll('.var-card')).map(card=>{
        const adults = parseInt(card.querySelector('.var-adults').value,10)||1;
        const children= parseInt(card.querySelector('.var-children').value,10)||0;
        const vprices = Array.from(card.querySelectorAll('.var-price')).map(i=> parseFloat(i.value)||0 );
        return {adults,children,prices:vprices};
      });

      const form = new FormData();
      form.append('action','rsv_update_price');
      form.append('nonce',nonceUpdate);
      form.append('type_id',currentType);
      form.append('start_date',selectedRange.start);
      form.append('end_date',selectedRange.end);
      form.append('periods', JSON.stringify(periods));
      form.append('base_prices', JSON.stringify(base_prices));
      form.append('variations', JSON.stringify(variations));

      fetch(ajax,{method:'POST',body:form,credentials:'same-origin'}).then(r=>r.json()).then(res=>{
        if(res && res.success){ calendar.refetchEvents(); priceModal.classList.remove('open'); }
        else{ alert('Error: '+ (res && (res.data && res.data.message || res.message) || 'unknown')); }
      }).catch(()=> alert('Network error'));
    };
  }

  if(createBtn){
    createBtn.addEventListener('click', ()=>{
      if(!selectedRange) return alert('Select a date range first');
      const url = ajax + '?action=rsv_admin_new_booking&type_id='+currentType+'&ci='+selectedRange.start+'&co='+selectedRange.end;
      window.open(url, '_blank');
    });
  }

  if(syncBtn){
    syncBtn.addEventListener('click', ()=>{
      const form=new FormData(); form.append('action','rsv_ical_sync'); form.append('type_id',currentType);
      fetch(ajax,{method:'POST',body:form,credentials:'same-origin'}).then(r=>r.json()).then(res=>{
        if(res && res.success){ syncRes.textContent='Imported '+(res.data && res.data.added ? res.data.added : 0)+' events.'; calendar.refetchEvents(); }
        else { syncRes.textContent='iCal sync failed'; }
      }).catch(()=> syncRes.textContent='Network error');
    });
  }
})();
