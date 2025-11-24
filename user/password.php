<?php // Public password reset page (no email; username→confirm→new password) ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="../assets/css/password.css" rel="stylesheet" />
</head>
<body>
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>

  <div class="shell">
    <section class="brand-panel d-none d-md-block">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-shield-lock-fill text-primary" style="font-size:1.6rem"></i>
        <div class="brand-title">MZE Cellular</div>
      </div>
      <div class="brand-sub">Account Recovery</div>
      <div class="badges">
        <span class="chip"><i class="bi bi-person-badge me-1"></i> Username-based</span>
        <span class="chip"><i class="bi bi-lock me-1"></i> No email required</span>
        <span class="chip"><i class="bi bi-shield-check me-1"></i> Secure</span>
      </div>
      <ul class="feature-list">
        <li><i class="bi bi-check-circle-fill"></i> Look up your account</li>
        <li><i class="bi bi-check-circle-fill"></i> Confirm your name</li>
        <li><i class="bi bi-check-circle-fill"></i> Set a new password</li>
      </ul>
    </section>

    <section class="login-card">
      <h3 class="login-title mb-3">Reset Password</h3>
      <div id="notice" class="alert d-none" role="alert"></div>

      <div id="step1">
        <label class="form-label">Username or Employee ID</label>
        <input type="text" class="form-control" id="username" placeholder="Enter your username" autocomplete="username">
        <div class="d-grid mt-3">
          <button class="btn btn-primary w-100" id="btnLookup"><i class="bi bi-search me-1"></i> Continue</button>
        </div>
      </div>

      <div id="step2" class="d-none">
        <div class="alert alert-info d-flex align-items-center gap-2" id="confirmMsg"><i class="bi bi-person-check"></i><span>Confirm account</span></div>
        <label class="form-label">New Password</label>
        <div class="input-group mb-1">
          <input type="password" class="form-control" id="newpass" autocomplete="new-password" minlength="6">
          <span class="input-group-text toggle-pass" id="toggle1" role="button" tabindex="0" aria-pressed="false"><i class="bi bi-eye"></i></span>
        </div>
        <div class="strength" id="meter"><span></span><span></span><span></span><span></span></div>
        <label class="form-label mt-2">Confirm New Password</label>
        <div class="input-group">
          <input type="password" class="form-control" id="newpass2" autocomplete="new-password" minlength="6">
          <span class="input-group-text toggle-pass" id="toggle2" role="button" tabindex="0" aria-pressed="false"><i class="bi bi-eye"></i></span>
        </div>
        <small class="text-muted">Use at least 8 characters with letters and numbers.</small>
        <div class="d-grid mt-3">
          <button class="btn btn-primary w-100" id="btnReset"><i class="bi bi-check2-circle me-1"></i> Reset Password</button>
        </div>
      </div>

      <div class="text-center mt-3">
        <a href="login.php" class="text-decoration-none">Back to Login</a>
      </div>
    </section>
  </div>

  <div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="confirmBody">Is this you?</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
          <button type="button" class="btn btn-primary" id="confirmYes">Yes, continue</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function byId(id){ return document.getElementById(id); }
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    let pendingUser = null;

    function showNotice(kind, text){
      const el = byId('notice');
      el.className = 'alert alert-'+kind;
      el.textContent = text;
      el.classList.remove('d-none');
      el.classList.remove('shake');
      requestAnimationFrame(()=> el.classList.add('shake'));
    }
    function strengthScore(s){
      let n=0; if(!s) return n; if(s.length>=8) n++; if(/[A-Z]/.test(s)) n++; if(/[0-9]/.test(s)) n++; if(/[^A-Za-z0-9]/.test(s)) n++; return n;
    }
    function updateMeter(){
      const v = byId('newpass').value; const score = strengthScore(v); const bars = byId('meter').querySelectorAll('span');
      bars.forEach((b,i)=>{ b.className = i<score ? 'on-'+score : ''; });
    }
    byId('newpass').addEventListener('input', updateMeter);
    function toggleBtn(btnId, inputId){
      const btn = byId(btnId), inp = byId(inputId);
      const set = (show)=>{ inp.type = show? 'text':'password'; btn.innerHTML = `<i class="bi ${show? 'bi-eye-slash':'bi-eye'}"></i>`; btn.setAttribute('aria-pressed', String(show)); };
      btn.addEventListener('click', ()=> set(inp.type==='password'));
      btn.addEventListener('keydown', (e)=>{ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); btn.click(); }});
      set(false);
    }
    toggleBtn('toggle1','newpass');
    toggleBtn('toggle2','newpass2');

    byId('btnLookup').addEventListener('click', async ()=>{
      const u = (byId('username').value||'').trim();
      if(!u){ showNotice('danger','Please enter your username.'); return; }
      showNotice('info','Checking…');
      try{
        const r = await fetch('../backend/reset_password.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({ op:'lookup', username:u }) });
        const j = await r.json();
        if(!j.success){ showNotice('danger', j.message||'User not found'); return; }
        pendingUser = j.user; // {username, emp_id, full_name}
        byId('confirmBody').textContent = `Is this you: ${j.user.full_name}?`;
        showNotice('success','User found. Please confirm.');
        modal.show();
      }catch(e){ showNotice('danger','Network error.'); }
    });

    byId('confirmYes').addEventListener('click', ()=>{
      modal.hide();
      byId('step1').classList.add('d-none');
      byId('confirmMsg').innerHTML = `<i class=\"bi bi-person-check me-1\"></i> Account: ${pendingUser.full_name}`;
      byId('step2').classList.remove('d-none');
      byId('newpass').focus();
    });

    byId('btnReset').addEventListener('click', async ()=>{
      const p1 = byId('newpass').value;
      const p2 = byId('newpass2').value;
      if(!p1 || p1.length < 6){ showNotice('danger','Password must be at least 6 characters.'); return; }
      if(p1 !== p2){ showNotice('danger','Passwords do not match.'); return; }
      showNotice('info','Saving…');
      try{
        const r = await fetch('../backend/reset_password.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({ op:'reset', username: pendingUser.username, new_password: p1 }) });
        const j = await r.json();
        if(!j.success){ showNotice('danger', j.message||'Failed to reset.'); return; }
        // Success + soft redirect to Login
        let secs = 5;
        showNotice('success', `Password reset successful. Redirecting to Login in ${secs}s…`);
        const iv = setInterval(()=>{ secs--; if(secs<=0){ clearInterval(iv); window.location.href='login.php'; } else { showNotice('success', `Password reset successful. Redirecting to Login in ${secs}s…`); } }, 1000);
      }catch(e){ showNotice('danger','Network error.'); }
    });
  </script>
</body>
</html>