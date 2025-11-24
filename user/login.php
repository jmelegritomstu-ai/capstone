<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MZE Cellular</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<link href="../assets/css/login.css" rel="stylesheet" />
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
      <div class="brand-sub">Secure workforce portal</div>
      <div class="badges">
        <span class="chip"><i class="bi bi-person-check me-1"></i> Account Executive</span>
        <span class="chip"><i class="bi bi-search me-1"></i> Auditor</span>
        <span class="chip"><i class="bi bi-lock me-1"></i> Encrypted</span>
      </div>
      <ul class="feature-list">
        <li><i class="bi bi-check-circle-fill"></i> Single sign-on ready</li>
        <li><i class="bi bi-check-circle-fill"></i> Role-based access</li>
        <li><i class="bi bi-check-circle-fill"></i> Audit-friendly logs</li>
      </ul>
    </section>

    <section class="login-card">
      <h3 class="login-title mb-3">Login</h3>
      <div id="alert" class="alert alert-danger d-none" role="alert"></div>
      <form id="loginForm">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" class="form-control" name="username" autocomplete="username" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="input-group">
            <input type="password" class="form-control" name="password" id="password" autocomplete="current-password" required />
            <span class="input-group-text toggle-pass" id="togglePass" title="Show/Hide" role="button" tabindex="0" aria-pressed="false" aria-controls="password"><i class="bi bi-eye"></i></span>
          </div>
        </div>
        <button id="submitBtn" type="submit" class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right me-1"></i> Login
        </button>
      </form>
      
    </section>
  </div>

  <script>
    const form = document.getElementById('loginForm');
    const alertBox = document.getElementById('alert');
    const submitBtn = document.getElementById('submitBtn');
    const toggleBtn = document.getElementById('togglePass');
    const pwd = document.getElementById('password');

    function setPasswordVisibility(show){
      pwd.setAttribute('type', show ? 'text' : 'password');
      toggleBtn.innerHTML = `<i class="bi ${show? 'bi-eye-slash':'bi-eye'}"></i>`;
      toggleBtn.setAttribute('aria-pressed', String(show));
    }
    toggleBtn.addEventListener('click', ()=>{
      const is = pwd.getAttribute('type') === 'password';
      setPasswordVisibility(is);
    });
    toggleBtn.addEventListener('keydown', (ev)=>{
      if(ev.key === 'Enter' || ev.key === ' '){ ev.preventDefault(); toggleBtn.click(); }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.classList.add('d-none');
      alertBox.classList.remove('shake');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Signing In...';
      const fd = new FormData(form);
      fd.append('op','login');
      try{
        const res = await fetch('../backend/auth.php', { method:'POST', body: fd });
        let data;
        try { data = await res.json(); } catch(_) { throw new Error('Login failed'); }
        if(!data || !data.success){ throw new Error(data?.message || 'Login failed'); }
        const target = data.redirect || (data.user?.role === 'account_executive' ? 'attendance.php' : 'dashboard.php');
        window.location.href = target;
      }catch(err){
        alertBox.textContent = err.message || 'Login failed';
        alertBox.classList.remove('d-none');
        // trigger shake
        requestAnimationFrame(()=>{ alertBox.classList.add('shake'); });
      }finally{
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Login';
      }
    });

    // If redirected with blocked=1, show friendly message and disable login
    (function checkBlocked(){
      try{
        const params = new URLSearchParams(window.location.search);
        if (params.get('blocked') === '1'){
          alertBox.textContent = 'Account disabled. Please contact HR or your administrator.';
          alertBox.classList.remove('d-none');
          submitBtn.disabled = true;
        }
      }catch(e){/* ignore */}
    })();
  </script>
</body>
</html>
