// assets/js/clock.js - Live Digital Clock for School Dashboard
document.addEventListener('DOMContentLoaded', () => {
    function updateClock() {
        const clockElements = document.querySelectorAll('.digital-clock-display');
        if (!clockElements.length) return;

        const now = new Date();
        
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        const dayName = days[now.getDay()];
        const dateNum = String(now.getDate()).padStart(2, '0');
        const monthName = months[now.getMonth()];
        const yearNum = now.getFullYear();

        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        const formattedDate = `${dayName}, ${dateNum} ${monthName} ${yearNum}`;
        const formattedTime = `${hours}:${minutes}:${seconds}`;

        clockElements.forEach(el => {
            el.innerHTML = `
                <div class="flex items-center gap-2.5 bg-slate-900/90 text-white px-3.5 py-1.5 rounded-xl border border-slate-800 shadow-sm text-xs sm:text-sm font-mono tracking-tight select-none">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="hidden md:inline text-slate-300 font-sans font-medium">${formattedDate}</span>
                    <span class="hidden md:inline text-slate-600">|</span>
                    <span class="font-bold text-emerald-400 bg-slate-800/80 px-2 py-0.5 rounded-md border border-slate-700/50">${formattedTime} <span class="text-[10px] text-slate-400">WIB</span></span>
                </div>
            `;
        });
    }

    updateClock();
    setInterval(updateClock, 1000);
});
