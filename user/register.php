<?php
// Public registration page for employee or auditor accounts
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/register.css" rel="stylesheet" />
</head>
<body>
  <div class="shell">
    <div class="head">
      <div class="brand">Create Account</div>
    </div>
    <div class="alert d-none" id="msg"></div>
    <div class="card p-4">
      <form id="regForm" class="row g-4" enctype="multipart/form-data">
        <div class="col-12">
          <div class="section-title">Account</div>
          <div class="sub mb-2">Set your credentials and link to an employee ID</div>
        </div>
  <div class="col-md-3 text-center">
          <img id="avatarPrev" class="avatar" src="https://via.placeholder.com/160x160.png?text=Preview" alt="Avatar Preview">
          <div class="mt-3">
            <input type="file" id="photoFile" name="profile_img" accept="image/*" class="form-control" />
            <div class="hint mt-2">Choose a profile image</div>
          </div>
        </div>
        <div class="col-md-9">
          <div class="row g-3 align-items-end">
            <div class="col-md-6">
              <label class="form-label">Username *</label>
              <input type="text" class="form-control" name="username" required maxlength="100" autocomplete="username">
            </div>
            <div class="col-md-6">
              <label class="form-label">Password *</label>
              <input type="password" class="form-control" name="password" required minlength="6" autocomplete="new-password">
            </div>
            <div class="col-md-6">
              <label class="form-label">Role *</label>
              <select class="form-select" name="role" id="roleSel">
                <option value="account_executive" selected>Account Executive</option>
                <option value="auditor">Auditor</option>
              </select>
            </div>
            <div class="col-md-6" id="empIdRow">
              <label class="form-label">Employee ID *</label>
              <input type="text" class="form-control" name="emp_id" required placeholder="EMP001">
            </div>
          </div>
        </div>

        <div class="col-12 pt-2">
          <div class="section-title">Employee Profile</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Full Name *</label>
          <input type="text" class="form-control" name="full_name" required maxlength="150">
        </div>
        <div class="col-md-6">
          <label class="form-label">Position *</label>
          <input type="text" class="form-control" name="position" id="positionField" required maxlength="100" placeholder="Sales Associate">
        </div>
        <div class="col-md-6">
          <label class="form-label">Gender</label>
          <select class="form-select" name="gender">
            <option value="">Select --</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Prefer not to say">Prefer not to say</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Birthday</label>
          <input type="date" class="form-control" name="birthday">
        </div>
        <div class="col-md-6" id="auditorField" style="display:none;">
          <label class="form-label">Auditor</label>
          <select class="form-select" name="auditor_name" id="auditorSelect">
            <option value="">-- Select auditor --</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact Number</label>
          <input type="text" class="form-control" name="contact_number" required placeholder="+63 912 345 6789">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" placeholder="name@company.com">
        </div>
        <div class="col-md-6">
          <label class="form-label">Date Hired</label>
          <input type="date" class="form-control" name="date_hired">
        </div>
        <div class="col-md-6">
          <label class="form-label">Branch Selection (City)</label>
          <select class="form-select" name="branch_city" id="branchCity">
            <option value="">-- Select City --</option>
            <option>Manila</option>
            <option>Quezon</option>
            <option>Caloocan</option>
            <option>Paranaque</option>
            <option>Marikina</option>
            <option>Taguig</option>
            <option>Antipolo</option>
            <option>Imus</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Branch Name</label>
          <input type="text" class="form-control" name="branch_name" id="branchName" list="branchNameList" placeholder="Select or type branch">
          <datalist id="branchNameList"></datalist>
          <div class="hint mt-1">Choose from list or type a new branch name</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Branch Location</label>
          <input type="text" class="form-control" name="branch_location" id="branchLocation" list="branchLocationList" placeholder="City / Area">
          <datalist id="branchLocationList"></datalist>
        </div>
        <div class="col-md-6">
          <label class="form-label">Contract Type</label>
          <select class="form-select" name="contract_type">
            <option value="">-- Select --</option>
            <option>Regular</option>
            <option>Probationary</option>
            <option>Contractual</option>
          </select>
        </div>
        <!-- Realign Shift Schedule and Status to sit on same row for consistent vertical alignment -->
        <div class="col-md-6">
          <label class="form-label">Shift Schedule</label>
          <input type="text" class="form-control" name="shift_schedule" id="edit_shift_schedule" placeholder="8:00 AM - 5:00 PM">
        </div>
        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="Active" selected>Active</option>
            <option value="On Leave">On Leave</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Preferred Day Off</label>
          <select class="form-select" name="dayoff_weekday">
            <option value="">-- Select --</option>
            <option value="1">Monday</option>
            <option value="2">Tuesday</option>
            <option value="3">Wednesday</option>
            <option value="4">Thursday</option>
            <option value="5">Friday</option>
            <option value="6">Saturday</option>
            <option value="7">Sunday</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Address</label>
          <textarea class="form-control" name="address" rows="3" placeholder="Street, City / Province / ZIP"></textarea>
        </div>
        <div class="col-12 d-flex gap-2 mt-1">
          <button type="submit" class="btn btn-primary flex-fill" id="btnCreate">Create Account</button>
          <a class="btn btn-outline-secondary" href="login.php">Back to Login</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    const roleSel = document.getElementById('roleSel');
    const empIdRow = document.getElementById('empIdRow');
    const auditorField = document.getElementById('auditorField');
    function adjustRoleUI(){
      // Show auditor supervisor field only for Account Executives (replacing old Employee role)
      const show = (roleSel.value === 'account_executive');
      auditorField.style.display = show ? '' : 'none';
      if (show) ensureAuditorOptionsLoaded();
    }
    roleSel.addEventListener('change', adjustRoleUI);
    adjustRoleUI();

    function showMsg(type, text){
      const m=document.getElementById('msg');
      m.className = 'alert alert-'+type;
      m.textContent = text;
      m.classList.remove('d-none');
    }

    // Live preview for uploaded file
    const photoFile = document.getElementById('photoFile');
    const avatarPrev = document.getElementById('avatarPrev');
    photoFile.addEventListener('change', (e)=>{
      const f = e.target.files && e.target.files[0];
      if(!f) return;
      const reader = new FileReader();
      reader.onload = ev => { avatarPrev.src = ev.target.result; };
      reader.readAsDataURL(f);
    });

  document.getElementById('regForm').addEventListener('submit', async (e)=>{
      e.preventDefault();
      // Ensure position is set from selected role before submitting
      try { syncPosition(); } catch(e) {}
      const fd = new FormData(e.target);
      const btn = document.getElementById('btnCreate');
      btn.disabled = true; btn.textContent = 'Creating…';
      try{
        const r = await fetch('../backend/register_user.php', { method:'POST', body: fd });
        const j = await r.json();
        if(!j.success){ showMsg('danger', j.message || 'Registration failed'); btn.disabled=false; btn.textContent='Create Account'; return; }
        showMsg('success', j.message || 'Success');
        setTimeout(()=>{ window.location.href = j.redirect || 'login.php'; }, 800);
      }catch(err){
        showMsg('danger','Network error');
        btn.disabled=false; btn.textContent='Create Account';
      }
    });

    // Auto-set position based on chosen role (remove duplicate roleSel declaration)
    const positionField = document.getElementById('positionField');
    const roleToPosition = {
      auditor: 'Auditor',
      account_executive: 'Account Executive'
    };
    // Branch dynamic options
  const branchCity = document.getElementById('branchCity');
  const branchName = document.getElementById('branchName');
  const branchNameList = document.getElementById('branchNameList');
  const branchLocation = document.getElementById('branchLocation');
  const branchLocationList = document.getElementById('branchLocationList');
    const branches = {
      'Manila': [
        'MZE BLUMENTRITT','MZE RECTO','MZE OUIAPO','MZE SAN JUAN','MZE GALAS','MZE DELA FUENTE','MZE PACO','MZE PRITIL','JOG PEDRO GIL'
      ],
      'Quezon': [
        'MZE E.RODRIGUEZ','MZE QUEZON AVENUE','MZE TIMOG','MZE KAMUNING'
      ],
      'Caloocan': [
        'MZE SANGANDAAN','MZE MAYPAJO'
      ],
      'Paranaque': [
        'MZE MALIBAY','MZE EVACOM','MZE VALLEY 1','MZE SOLEDAD','MZE NAIA PASAY','MZE CAA- LAS PINAS','MZE FOURTH ESTATE','MZE RFC MOONWALK','MZE RFC BICUTAN'
      ],
      'Marikina': ['MZE SUMULONG'],
      'Taguig': ['MZE TAGUIG'],
      'Antipolo': ['MZE RFC CAINTA'],
      'Imus': ['JC IMUS']
    };
    const allBranches = Object.values(branches).flat();
    function fillBranchNameDatalist(city){
      branchNameList.innerHTML = '';
      const list = city && branches[city] ? branches[city] : allBranches;
      list.forEach(b=>{
        const opt = document.createElement('option');
        opt.value = b; branchNameList.appendChild(opt);
      });
    }
    // Branch location suggestions: cities + common areas
    const locationSuggestions = Array.from(new Set([
      ...Object.keys(branches),
      'Las Pinas','Pasay','Marikina','Taguig','Antipolo','Cainta','Moonwalk','Bicutan','Valley 1','Soledad','NAIA','San Juan','Quiapo','Galas','Pritil','Paco','Dela Fuente','Recto','Blumentritt','E. Rodriguez','Timog','Kamuning','Quezon Avenue','Sangandaan','Maypajo','Evacom','Malibay','Fourth Estate','Sumulong'
    ]));
    function fillBranchLocationDatalist(){
      branchLocationList.innerHTML = '';
      locationSuggestions.forEach(loc=>{
        const opt = document.createElement('option');
        opt.value = loc; branchLocationList.appendChild(opt);
      });
    }
    function onCityChange(){
      const city = branchCity.value;
      fillBranchNameDatalist(city);
      if (city) branchLocation.value = city;
    }
    branchCity.addEventListener('change', onCityChange);
    // initialize datalists on load
    fillBranchNameDatalist(branchCity.value);
    fillBranchLocationDatalist();
    function syncPosition(){
      const r = roleSel.value;
      if (roleToPosition[r]) {
        positionField.value = roleToPosition[r];
      }
    }
    roleSel.addEventListener('change', syncPosition);
    // initialize on load
    syncPosition();
    // Auto select Saturday as default dayoff for account executives (optional heuristic)
    const roleSelLocal = document.getElementById('roleSel');
    const dayoffSel = document.querySelector('select[name="dayoff_weekday"]');
    function setDefaultDayoff(){
      if(dayoffSel && !dayoffSel.value && roleSelLocal.value==='account_executive'){ dayoffSel.value='6'; }
    }
    roleSelLocal.addEventListener('change', setDefaultDayoff);
    setDefaultDayoff();

    // Load auditors-only into auditorSelect, preferring auditors for selected branch/city
    let __auditors_loaded_reg = false;
    async function ensureAuditorOptionsLoaded(){
      const sel = document.getElementById('auditorSelect');
      if(!sel) return;
      // get current branch filters
      const bname = (document.getElementById('branchName') && document.getElementById('branchName').value) ? encodeURIComponent(document.getElementById('branchName').value) : '';
      const bcity = (document.getElementById('branchCity') && document.getElementById('branchCity').value) ? encodeURIComponent(document.getElementById('branchCity').value) : '';
      // request auditors filtered by branch if available
      let url = '../backend/get_auditors.php';
      const params = [];
      if (bname) params.push('branch_name=' + bname);
      if (bcity) params.push('branch_city=' + bcity);
      if (params.length) url += '?' + params.join('&');
      try{
        const res = await fetch(url);
        const j = await res.json();
        if(!j.success) return;
        sel.innerHTML = '<option value="">-- Select auditor --</option>';
        (j.data||[]).forEach(a=>{
          const opt = document.createElement('option');
          opt.value = a.full_name;
          opt.textContent = a.full_name;
          // stash emp_id and branch info on option for later
          if(a.emp_id) opt.dataset.empId = a.emp_id;
          if(a.branch_name) opt.dataset.branchName = a.branch_name;
          if(a.branch_city) opt.dataset.branchCity = a.branch_city;
          sel.appendChild(opt);
        });
        // If there is exactly one match and it matches branch, auto-select and disable to avoid user confusion
        if ((j.data || []).length === 1) {
          sel.value = j.data[0].full_name;
          sel.disabled = true;
        } else {
          sel.disabled = false;
        }
        __auditors_loaded_reg = true;
      }catch(e){ console.warn('Failed to load auditors for register', e); }
    }
  </script>
</body>
</html>
