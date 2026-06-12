// Alarm system for baby-tracker
function playAlarmSound() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    [0, 0.4, 0.8, 1.2].forEach(offset => {
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 880;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0, ctx.currentTime + offset);
      gain.gain.linearRampToValueAtTime(0.4, ctx.currentTime + offset + 0.05);
      gain.gain.linearRampToValueAtTime(0, ctx.currentTime + offset + 0.3);
      osc.start(ctx.currentTime + offset);
      osc.stop(ctx.currentTime + offset + 0.35);
    });
  } catch(e) {}
}

function showAlarmBanner(log) {
  const banners = document.getElementById('alarmBanners');
  if (!banners) return;
  const div = document.createElement('div');
  div.className = 'alarm-banner';
  div.innerHTML = `
    <div style="font-size:2rem">🔔</div>
    <div class="flex-grow-1">
      <strong style="display:block;font-size:0.95rem">Time to check on ${log.category}!</strong>
      <span style="font-size:0.8rem;opacity:0.9">
        Logged at ${new Date(log.logged_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}
      </span>
    </div>
    <button style="border:none;background:white;color:#e03e3e;border-radius:8px;padding:6px 12px;font-weight:800;cursor:pointer"
      onclick="this.closest('.alarm-banner').remove()">Done</button>
  `;
  banners.appendChild(div);
  playAlarmSound();
}

function updateCountdowns() {
  if (typeof activeAlarmsData === 'undefined') return;
  activeAlarmsData.forEach(log => {
    const el = document.getElementById('alarm-' + log.id);
    if (!el) return;
    const loggedAt  = new Date(log.logged_at.replace(' ', 'T'));
    const targetAt  = new Date(loggedAt.getTime() + log.alarm_minutes * 60000);
    const msLeft    = targetAt - new Date();
    if (msLeft <= 0) {
      el.textContent = '🔔 Time to check!';
      el.style.background = '#ffe0e0';
      el.style.color = '#c62828';
    } else {
      const mins = Math.floor(msLeft / 60000);
      const hrs  = Math.floor(mins / 60);
      const rem  = mins % 60;
      el.textContent = '⏰ ' + (hrs > 0 ? hrs + 'h ' + rem + 'm' : mins + 'm') + ' left';
    }
  });
}

// Schedule alarm triggers
if (typeof activeAlarmsData !== 'undefined') {
  activeAlarmsData.forEach(log => {
    const loggedAt = new Date(log.logged_at.replace(' ', 'T'));
    const targetAt = new Date(loggedAt.getTime() + log.alarm_minutes * 60000);
    const msLeft   = targetAt - new Date();
    if (msLeft > 0) {
      setTimeout(() => {
        showAlarmBanner(log);
        // Mark as triggered via API
        fetch('/baby-tracker/api/alarm.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({id: log.id})
        });
      }, msLeft);
    }
  });
}

updateCountdowns();
setInterval(updateCountdowns, 30000);
