
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MZE Cellular</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Poppins", sans-serif;
    }

    body {
      height: 100vh;
      width: 100%;
      background: linear-gradient(180deg, #3638aaff, #868288ff);
      display: flex;
      justify-content: space-between;
      align-items: center;
      overflow: hidden;
      color: white;
      position: relative;
    }

    /* --- Animated Gradient Overlay --- */
    body::before {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.08), transparent 60%),
                  radial-gradient(circle at 70% 70%, rgba(255,255,255,0.05), transparent 60%);
      animation: moveGradient 10s ease-in-out infinite alternate;
      z-index: 0;
    }

    @keyframes moveGradient {
      from { transform: translate(0, 0); }
      to { transform: translate(30px, 30px); }
    }

    /* --- LOGIN BUTTON --- */
    .login-btn {
      position: absolute;
      top: 30px;
      right: 50px;
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      border: none;
      color: white;
      padding: 10px 25px;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      z-index: 5;
    }

    .login-btn:hover {
      background: white;
      color: #3c005a;
      transform: scale(1.05);
      box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
    }

    /* --- LEFT PANEL --- */
    .left {
      flex: 1;
      padding-left: 100px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 20px;
      z-index: 1;
    }

    .left .logo {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .left .logo i {
      font-size: 60px;
      background: rgba(255, 255, 255, 0.15);
      padding: 20px;
      border-radius: 50%;
      backdrop-filter: blur(10px);
      box-shadow: 0 0 15px rgba(255, 255, 255, 0.15);
    }

    .left h1 {
      font-size: 60px;
      font-weight: 700;
      background: linear-gradient(90deg, #ffffff, #d7b0ff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .left p {
      font-size: 18px;
      max-width: 400px;
      color: rgba(255, 255, 255, 0.8);
    }

    /* --- RIGHT PANEL (CARDS) --- */
    .right {
      flex: 1.2;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 30px;
      flex-wrap: wrap;
      padding-right: 100px;
      z-index: 1;
    }

    .card {
      width: 230px;
      height: 320px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 25px rgba(0, 0, 0, 0.3);
      overflow: hidden;
      transition: transform 0.6s ease, box-shadow 0.6s ease;
      position: relative;
      cursor: pointer;
      color: white;
    }

    .card img {
      width: 100%;
      height: 60%;
      object-fit: cover;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .card h3 {
      margin: 15px 20px 5px 20px;
      font-size: 18px;
      font-weight: 600;
    }

    .card p {
      margin: 0 20px;
      font-size: 14px;
      color: rgba(255, 255, 255, 0.8);
    }

    .card:hover {
      transform: translateY(-10px) rotate3d(1, 1, 0, 6deg);
      box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
    }

    /* --- FLOAT ANIMATION --- */
    @keyframes floaty {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }
    .card:nth-child(odd) { animation: floaty 5s ease-in-out infinite; }
    .card:nth-child(even) { animation: floaty 6s ease-in-out infinite; }

    /* --- LOGIN MODAL --- */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(6px);
      display: none;
      justify-content: center;
      align-items: center;
      animation: fadeIn 0.3s ease;
      z-index: 9999;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(25px);
      padding: 40px 50px;
      border-radius: 20px;
      box-shadow: 0 0 35px rgba(0, 0, 0, 0.5);
      text-align: left;
      color: white;
      transform: scale(0.9);
      animation: zoomIn 0.3s ease forwards;
      width: 350px;
      z-index: 10000;
    }

    @keyframes zoomIn {
      to { transform: scale(1); }
    }

    .modal h2 {
      text-align: center;
      margin-bottom: 25px;
      font-weight: 600;
    }

    .input-group {
      margin-bottom: 20px;
    }

    .input-group label {
      display: block;
      font-size: 14px;
      color: rgba(255, 255, 255, 0.9);
      margin-bottom: 6px;
    }

    .input-group input {
      width: 100%;
      padding: 12px 45px 12px 15px;
      border: none;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.9);
      color: #000;
      font-size: 14px;
      outline: none;
    }

    .password-wrapper {
      position: relative;
    }

    .password-wrapper i {
      position: absolute;
      right: 15px;
      top: 38px;
      font-size: 18px;
      color: #000;
      cursor: pointer;
      transition: 0.3s;
    }

    .password-wrapper i:hover {
      color: #3c005a;
    }

    .remember {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 25px;
    }

    .remember input {
      accent-color: #7d2ae8;
      width: 16px;
      height: 16px;
      cursor: pointer;
    }

    .remember label {
      font-size: 14px;
      color: rgba(255, 255, 255, 0.9);
      cursor: pointer;
    }

    .login-submit {
      background: linear-gradient(90deg, #7d2ae8, #a764dc);
      border: none;
      color: white;
      padding: 12px 40px;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      transition: 0.3s;
      width: 100%;
    }

    .login-submit:hover {
      background: white;
      color: #3c005a;
      box-shadow: 0 0 20px rgba(255,255,255,0.3);
    }

    footer {
      position: absolute;
      bottom: 20px;
      left: 100px;
      color: rgba(255, 255, 255, 0.7);
      font-size: 14px;
    }

    @media (max-width: 900px) {
      body {
        flex-direction: column;
        padding: 40px 0;
      }
      .left {
        align-items: center;
        text-align: center;
        padding-left: 0;
      }
      .right {
        padding-right: 0;
      }
    }
  </style>
</head>

<body>
  <!-- Login Button -->
 <a href="user/login.php" class="login-btn">Login</a>


  <!-- Left Side -->
  <div class="left">
    <div class="logo">
      <i class="bi bi-phone"></i>
      <h1>MZE Cellular</h1>
    </div>
    <p>Innovating connections, empowering performance across all MZE branches nationwide.</p>
  </div>

  <!-- Right Side Cards -->
  <div class="right">
    <div class="card">
      <img src="https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=600&q=60" alt="">
      <h3>Smart Devices</h3>
      <p>Experience innovation through every device we offer.</p>
    </div>

    <div class="card">
      <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=600&q=60" alt="">
      <h3>Team Excellence</h3>
      <p>The people behind MZE’s nationwide success and service.</p>
    </div>

    <div class="card">
      <img src="https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=600&q=60" alt="">
      <h3>Branch Network</h3>
      <p>Connected across cities — find your nearest MZE store.</p>
    </div>

    <div class="card">
      <img src="https://images.unsplash.com/photo-1541532713592-79a0317b6b77?auto=format&fit=crop&w=600&q=60" alt="">
      <h3>Support & Solutions</h3>
      <p>Empowering your experience with expert care and innovation.</p>
    </div>
  </div>


</body>
</html>
