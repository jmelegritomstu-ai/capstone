<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/attendanceuser.css" rel="stylesheet">
</head>
<body>
  <div class="container-narrow">
    <div class="text-center mb-3">
      <h3 class="fw-bold text-primary">MZE Cellular Time-in</h3>
    </div>
    <div class="card p-4">
      <div class="d-flex align-items-center mb-3">
        <i class="bi bi-person-badge me-2 text-primary"></i>
        <div class="fw-semibold" id="empName">-</div>
        <div class="ms-auto sub" id="empId"></div>
      </div>
      <div class="row g-3 mb-2">
        <div class="col-6">
          <div class="small sub">Branch</div>
          <div id="empBranch" class="d-flex align-items-center gap-2">
            <span id="empBranchText">-</span>
            <select id="branchSelect" class="form-select form-select-sm d-none" style="max-width:280px"></select>
            <button id="editBranchBtn" type="button" class="btn btn-sm btn-light border">Edit</button>
          </div>
        </div>
        <div class="col-6">
          <div class="small sub">Location</div>
          <div id="empLoc"><span id="empLocText">-</span></div>
        </div>
      </div>
      <div class="alert d-none" id="msg"></div>
      <div class="alert alert-info d-none" id="holidayMsg"></div>
      <div class="d-flex gap-2">
        <button id="btnIn" class="btn btn-success flex-fill"><i class="bi bi-box-arrow-in-right me-1"></i> Time In</button>
      </div>
    </div>
    <div class="text-end mt-3">
      <a id="viewRecordsBtn" href="attendance_record.php" class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true"><i class="bi bi-journal-text me-1"></i> View Records</a>
    </div>
  </div>

  <script>
    // Track branch/location override even after hiding the select
    let initialBranch = '';
    let initialLoc = '';
    let branchOverride = null; // string or null
    let locationOverride = null; // string or null
    
    // Branch options mapping (Area -> Branches) and reverse lookup
    const areaToBranches = {
      'Manila': ['MZE BLUMENTRITT','MZE RECTO','MZE OUIAPO','MZE SAN JUAN','MZE GALAS','MZE DELA FUENTE','MZE PACO','MZE PRITIL','JOG PEDRO GIL'],
      'QC': ['MZE E.RODRIGUEZ','MZE QUEZON AVENUE','MZE TIMOG','MZE KAMUNING'],
      'Caloocan': ['MZE SANGANDAAN','MZE MAYPAJO'],
      'Paranaque': ['MZE MALIBAY','MZE EVACOM','MZE VALLEY 1','MZE SOLEDAD','MZE NAIA PASAY','MZE CAA- las pinas','MZE FOURTH ESTATE','MZE RFC MOONWALK','MZE RFC BICUTAN'],
      'Marikina': ['MZE SUMULONG- marikina'],
      'Taguig': ['MZE TAGUIG'],
      'Cainta/Antipolo': ['MZE RFC CAINTA - antipolo'],
      'Cavite': ['JC IMUS']
    };
    const branchToArea = Object.entries(areaToBranches).reduce((acc,[area,branches])=>{branches.forEach(b=>acc[b]=area);return acc;},{});

    // Real API wrappers
    async function me(){
      try{
        const res = await fetch('../backend/diag_session_employee.php', { credentials: 'same-origin' });
        const j = await res.json();
        if (j && j.success) {
          return { success: true, role: j.session && j.session.role ? j.session.role : null };
        }
        return { success: false };
      } catch(e){
        console.error('me() error', e);
        return { success: false };
      }
    }

    async function scanSelf(){
      try{
        const body = new FormData();
        body.append('op','scan_self');
        const res = await fetch('../backend/attendance_api.php', { method: 'POST', body, credentials: 'same-origin' });
        const j = await res.json();
        return j;
      } catch(e){
        console.error('scanSelf error', e);
        return { success: false, message: 'Network error' };
      }
    }

    async function logSelf(action){
      try{
        const body = new FormData();
        body.append('op','log_self');
        body.append('action', action);
        // include branch override when user edited branch
        if (branchOverride) body.append('branch_name_override', branchOverride);
        if (locationOverride) body.append('branch_location_override', locationOverride);
        const res = await fetch('../backend/attendance_api.php', { method: 'POST', body, credentials: 'same-origin' });
        const j = await res.json();
        return j;
      } catch(e){
        console.error('logSelf error', e);
        return { success: false, message: 'Network error' };
      }
    }

    function showMsg(type, text){
      const el = document.getElementById('msg');
      el.className = 'alert alert-' + type; 
      el.textContent = text; 
      el.classList.remove('d-none');
    }

    // Centralized button state handler. Pass `isDayOff===true` to force Day Off appearance.
    function updateBtnState(isDayOff, suggest, worked = false){
      const inBtn = document.getElementById('btnIn');
      // Day Off state: primary styling and disabled. Mark via dataset so it can be cleared later.
      if (isDayOff) {
        inBtn.classList.remove('btn-success', 'btn-danger');
        inBtn.classList.add('btn-primary');
        inBtn.innerHTML = '<i class="bi bi-calendar-x me-1"></i> Day Off';
        inBtn.disabled = true;
        inBtn.dataset.dayoff = '1';
        // If user already worked today on a day-off, consider records accessible
        updateViewRecordsAccess(worked ? 'out' : 'done');
        return;
      }

      // Normal day: ensure Day Off marker cleared and normal enable/disable based on suggestion
      inBtn.classList.remove('btn-primary', 'btn-danger');
      inBtn.classList.add('btn-success');
      inBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Time In';
      inBtn.disabled = (suggest === 'out' || suggest === 'done');
      delete inBtn.dataset.dayoff;
      updateViewRecordsAccess(suggest);
    }

    // Backwards-compatible wrapper used elsewhere in the script
    function setButtons(suggest){ updateBtnState(false, suggest); }

    function updateViewRecordsAccess(suggest){
      const vr = document.getElementById('viewRecordsBtn');
      if(!vr) return;
      if(suggest === 'out' || suggest === 'done'){ // user has timed in today (open or completed)
        vr.classList.remove('disabled');
        vr.setAttribute('aria-disabled','false');
      } else {
        vr.classList.add('disabled');
        vr.setAttribute('aria-disabled','true');
      }
    }

    function populateBranchSelect(currentBranch, currentLoc){
      const sel = document.getElementById('branchSelect');
      sel.innerHTML = '<option value="">Select branch...</option>' +
        Object.entries(areaToBranches).map(([area, list])=>{
          const opts = list.map(b=>`<option value="${b}" ${b===currentBranch?'selected':''}>${b}</option>`).join('');
          return `<optgroup label="${area}">${opts}</optgroup>`;
        }).join('');
      
      // Toggle button
      const btn = document.getElementById('editBranchBtn');
      btn.addEventListener('click', ()=>{
        const isEditing = !sel.classList.contains('d-none');
        if(isEditing){
          // Save selection back to labels
          const val = sel.value;
          if(val){
            document.getElementById('empBranchText').textContent = val;
            const area = branchToArea[val] || currentLoc || '-';
            document.getElementById('empLocText').textContent = area;
            // Persist overrides for Time In even after select is hidden
            if (val !== initialBranch) {
              branchOverride = val;
              locationOverride = area;
            } else {
              branchOverride = null;
              locationOverride = null;
            }
          }
          sel.classList.add('d-none');
          btn.textContent = 'Edit';
        }else{
          sel.classList.remove('d-none');
          btn.textContent = 'Save';
        }
      });
      
      sel.addEventListener('change', ()=>{
        const val = sel.value;
        document.getElementById('empBranchText').textContent = val || '-';
        const area = val ? (branchToArea[val] || '-') : (currentLoc || '-');
        document.getElementById('empLocText').textContent = area;
        // Update overrides live while editing; will be finalized on Save
        if (val && val !== initialBranch) {
          branchOverride = val;
          locationOverride = area;
        } else {
          branchOverride = null;
          locationOverride = null;
        }
      });
    }

    // Initialize the page
    (async()=>{
      try{
        const auth = await me();
        if(!auth.success){ 
          console.log('Not authenticated');
          return; 
        }
        if(auth.role !== 'account_executive'){ 
          console.log('Not an account executive');
          return; 
        }
        
        const data = await scanSelf();
        if(!data.success){ 
          showMsg('danger', data.message||'Failed to load employee'); 
          return; 
        }
        
        const e = data.employee || {};
        document.getElementById('empName').textContent = e.full_name || '-';
        document.getElementById('empId').textContent = e.emp_id ? `ID: ${e.emp_id}` : '';
        document.getElementById('empBranchText').textContent = e.branch_name || '-';
        document.getElementById('empLocText').textContent = e.branch_location || '-';

        // On Leave handling: disable time-in similarly to day off, but distinct label
        const inBtn = document.getElementById('btnIn');
        const isOnLeave = (data.is_onleave_today === 1) || (data.suggested_action === 'onleave') || ((e.status||'').toLowerCase() === 'on leave');
        if (isOnLeave) {
          inBtn.classList.remove('btn-success','btn-danger','btn-primary');
          inBtn.classList.add('btn-secondary');
          inBtn.innerHTML = '<i class="bi bi-airplane me-1"></i> On Leave';
          inBtn.disabled = true;
          inBtn.dataset.onleave = '1';
          showMsg('info','On Leave: Time-in disabled today.');
          // Allow records if already have a log (rare for leave) based on suggested action
          updateViewRecordsAccess(data.suggested_action);
          return; // Skip branch/day-off logic
        }

        // Holiday advisory (non-blocking): show info banner but do NOT disable Time In.
        try {
          if (data.is_holiday_today === 1) {
            const hd = document.getElementById('holidayMsg');
            const dates = (Array.isArray(data.holiday_dates) && data.holiday_dates.length) ? data.holiday_dates.join(', ') : 'today';
            hd.classList.remove('d-none');
            hd.className = 'alert alert-info';
            hd.textContent = (data.holiday_message && data.holiday_message.length) ? data.holiday_message + ' (' + dates + ')' : 'Holiday advisory: today is a holiday ('+dates+'). You may still Time In.';
          }
        } catch(hErr){ console.warn('Holiday advisory error', hErr); }
        
        // Populate branch options
        initialBranch = e.branch_name || '';
        initialLoc = e.branch_location || '';
        populateBranchSelect(initialBranch, initialLoc);
        // Initialize buttons for suggested action (will be adjusted by day-off logic below)
        setButtons(data.suggested_action);
        
        // Day-off banner logic
        try {
          const serverIsDayOff = (typeof data.is_dayoff_today !== 'undefined') ? (data.is_dayoff_today === 1) : null;
          const prefW = (e.dayoff_weekday && e.dayoff_weekday >=1 && e.dayoff_weekday <=6) ? e.dayoff_weekday : null; // 1=Mon .. 6=Sun
          const today = new Date();
          const y = today.getFullYear(); 
          const m = today.getMonth();
          
          // Build working days (include Sunday if selected as preferred day-off)
          const workDays = []; 
          for(let d = 1; d <= 31; d++){ 
            const dt = new Date(y, m, d); 
            if(dt.getMonth() !== m) break; 
            const w = dt.getDay();
            // If prefW==6 treat Sunday (w===0) as candidate; otherwise skip Sunday from working set
            if(w === 0 && prefW !== 6) continue;
            workDays.push(dt);
          }
          
          const firstHalf = workDays.filter(d => d.getDate() <= 15);
          const secondHalf = workDays.filter(d => d.getDate() >= 16);
          
          function pick(days){
            // Prefer the LAST occurrence of preferred weekday in the half-month,
            // else the LAST Saturday, else the LAST working day.
            let preferred = null, saturday = null, last = null;
            for(const d of days){
              const w = d.getDay();
              last = d;
              if(w === 6) saturday = d; // keep last seen Saturday
              if(prefW !== null){
                if((prefW >= 1 && prefW <= 5 && w === prefW) || (prefW === 6 && w === 0)) preferred = d; // map 6 to Sunday
              }
            }
            if(preferred) return preferred;
            if(saturday) return saturday;
            return last;
          }
          
          const dayoff1 = firstHalf.length ? pick(firstHalf) : null;
          const dayoff2 = secondHalf.length ? pick(secondHalf) : null;
          const fmt = d => d.toISOString().slice(0,10);
          const todayIso = fmt(today);
          let isDayOffToday = (dayoff1 && fmt(dayoff1) === todayIso) || (dayoff2 && fmt(dayoff2) === todayIso);
          if (serverIsDayOff !== null) { isDayOffToday = !!serverIsDayOff; }
          
          const btnIn = document.getElementById('btnIn');
          if(isDayOffToday){
            const msgEl = document.getElementById('msg');
            // Determine if already timed in from suggested action (out or done means worked a day-off)
            const worked = (data.suggested_action === 'out' || data.suggested_action === 'done');
            msgEl.className = worked ? 'alert alert-warning' : 'alert alert-info';
            msgEl.innerHTML = worked
              ? '<strong>Day Off (worked):</strong> You are scheduled off today but have a time-in.'
              : '<strong>Scheduled Day Off:</strong> You are not required to time in today.';
            msgEl.classList.remove('d-none');

            // Use centralized updater so Day Off state is tracked and can be cleared later
            updateBtnState(true, data.suggested_action, worked);
          } else {
            // Normal day - ensure button is properly enabled
            updateBtnState(false, data.suggested_action);
          }
          } catch(dayoffErr) { 
          console.warn('Dayoff banner error', dayoffErr); 
          // If dayoff logic fails, fall back to normal button state
          try { updateBtnState(false, data.suggested_action); } catch(e){}
        }
      } catch(err) { 
        showMsg('danger', 'Error initializing page'); 
        console.error(err);
      }
    })();

    // Fixed Time In button click handler
    document.getElementById('btnIn').addEventListener('click', async ()=>{
      const inBtn = document.getElementById('btnIn');
      
      // Check if button is disabled due to day-off marker
      if (inBtn.dataset && (inBtn.dataset.dayoff === '1' || inBtn.dataset.onleave === '1')) {
        return; // Don't proceed if it's a day-off
      }
      
      inBtn.disabled = true;
      try {
        const res = await logSelf('in');
          if (res.success) {
          showMsg('success', 'Time in recorded');
          // After successful time-in, enable records access
          updateViewRecordsAccess('out');
          // Keep button disabled after successful time-in
          inBtn.disabled = true;
        } else {
            if (res.dayoff) {
            // Server indicates this is a dayoff — reflect centralized day-off state
            showMsg('info', res.message || 'Scheduled Day Off: time in disabled');
            updateBtnState(true, 'done', false);
          } else {
            showMsg('danger', res.message || 'Failed to time in');
            inBtn.disabled = false; // Re-enable on failure
          }
        }
      } catch (err) {
        showMsg('danger', 'Network error');
        inBtn.disabled = false; // Re-enable on network error
      }
    });

    // Intercept click when disabled to prevent navigation
    const vr = document.getElementById('viewRecordsBtn');
    if(vr){
      vr.addEventListener('click', (e)=>{
        if(vr.classList.contains('disabled')){
          e.preventDefault();
          showMsg('warning', 'You need to Time In first before viewing records.');
        }
      });
    }
  </script>
</body>
</html>