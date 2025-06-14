document.addEventListener("DOMContentLoaded", () => {
  const tg = window.Telegram?.WebApp;
  const user = tg?.initDataUnsafe?.user;

  const usernameElem = document.getElementById("username");
  const timerElem = document.getElementById("timer");
  const btn = document.getElementById("collect-btn");
  const progressBar = document.getElementById("btn-progress");
  const label = document.getElementById("btn-label");

  const API_BASE = "/api";
  const COOLDOWN_MS = 6 * 60 * 60 * 1000;

  if (!user) {
    usernameElem.innerText = "Ошибка авторизации";
    timerElem.innerText = "Запустите мини-игру через Telegram";
    return;
  }

  let interval;

  fetch(`${API_BASE}/entry.php`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      telegram_id: user.id,
      username: user.username,
      first_name: user.first_name,
      last_name: user.last_name,
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      let nextClaimAt = Date.now();
      if (data.next_claim_at) {
        const parsed = new Date(data.next_claim_at).getTime();
        if (!isNaN(parsed)) nextClaimAt = parsed;
      }

      startTimer(nextClaimAt);
    });

  btn.addEventListener("click", () => {
    fetch(`${API_BASE}/claim.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ telegram_id: user.id }),
    })
      .then((res) => {
        if (!res.ok)
          return res.json().then((err) => {
            throw new Error(err.error || "Ошибка сервера");
          });
        return res.json();
      })
      .then((card) => {
        alert(`Вы получили: ${card.name} [${card.rarity}]`);

        const nextClaimAt = Date.now() + COOLDOWN_MS;
        clearInterval(interval);
        startTimer(nextClaimAt);
      })
      .catch((err) => {
        alert(err.message);
        console.error("[ERROR] /api/claim:", err);
      });
  });

  function startTimer(nextClaimAt) {
    function updateTimer() {
      const now = Date.now();
      const diff = nextClaimAt - now;

      if (diff <= 0) {
        clearInterval(interval);
        timerElem.innerText = "Можно собрать карточку!";
        btn.disabled = false;
        progressBar.style.width = "100%";
        label.innerText = "Собрать";
        label.style.color = "#fff";
      } else {
        const hours = Math.floor(diff / 3600000);
        const minutes = Math.floor((diff % 3600000) / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);

        const h = hours;
        const m = String(minutes).padStart(2, "0");
        const s = String(seconds).padStart(2, "0");

        timerElem.innerText = `${h}:${m}:${s}`;

        const progress = 1 - diff / COOLDOWN_MS;
        const percentage = (progress * 100).toFixed(2);

        progressBar.style.width = `${percentage}%`;
        label.innerText = `${Math.floor(percentage)}%`;

        const colorValue = Math.floor(255 - progress * 255);
        label.style.color = `rgb(${colorValue}, ${colorValue}, ${colorValue})`;

        btn.disabled = true;
      }
    }

    updateTimer();
    interval = setInterval(updateTimer, 1000);
  }
});
