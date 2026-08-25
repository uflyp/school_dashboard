/**
 * School Dashboard V2.0 - Interactive Onboarding Walkthrough & Tour
 */

const tourSteps = [
    {
        title: "Selamat Datang di Portal Sekolah V2.0 👋",
        badge: "LANGKAH 1 dari 5",
        content: "Selamat datang di Sistem Informasi Dashboard Sekolah terpadu. Portal ini dirancang untuk mempermudah pengelolaan akademik, data siswa, presensi, jadwal, dan iuran sekolah secara real-time.",
        icon: "fa-graduation-cap",
        color: "from-indigo-500 to-purple-600"
    },
    {
        title: "Navigasi & Menu Utama 📍",
        badge: "LANGKAH 2 dari 5",
        content: "Gunakan sidebar di sebelah kiri layar Anda untuk berpindah modul. Akses kontrol telah disesuaikan secara khusus sesuai dengan Role akun Anda (Admin, Guru, Siswa, Keuangan, atau Kepala Sekolah).",
        icon: "fa-compass",
        color: "from-blue-500 to-indigo-600"
    },
    {
        title: "Waktu Real-time & Header Status ⏰",
        badge: "LANGKAH 3 dari 5",
        content: "Header bagian atas menampilkan Jam Digital WIB secara live serta indikator status login Anda. Seluruh log aktivitas dan sesi login terlindungi secara otomatis.",
        icon: "fa-clock",
        color: "from-emerald-500 to-teal-600"
    },
    {
        title: "Pengaturan Profil & Foto Perangkat 🖼️",
        badge: "LANGKAH 4 dari 5",
        content: "Klik tombol Foto Profil di sudut kanan atas header kapan saja untuk memperbarui foto profil perangkat Anda dari laptop atau smartphone.",
        icon: "fa-user-gear",
        color: "from-amber-500 to-orange-600"
    },
    {
        title: "Panduan Fitur Siap Digunakan! 🚀",
        badge: "LANGKAH 5 dari 5",
        content: "Anda selalu dapat membuka kembali tour panduan ini dengan mengklik tombol 'Panduan' di header kapan pun Anda membutuhkan bantuan. Selamat bekerja!",
        icon: "fa-circle-check",
        color: "from-cyan-500 to-blue-600"
    }
];

let currentTourStep = 0;

function renderTourModal() {
    let modal = document.getElementById('interactive-tour-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'interactive-tour-modal';
        modal.className = 'fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4 transition-all duration-300';
        document.body.appendChild(modal);
    }

    const step = tourSteps[currentTourStep];
    const isFirst = currentTourStep === 0;
    const isLast = currentTourStep === tourSteps.length - 1;

    modal.innerHTML = `
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative overflow-hidden transform transition-all scale-100">
            <!-- Top Gradient Accents -->
            <div class="absolute -top-12 -right-12 w-36 h-36 bg-gradient-to-br ${step.color} opacity-20 rounded-full blur-2xl pointer-events-none"></div>

            <div class="flex items-center justify-between mb-4">
                <span class="px-3 py-1 rounded-full text-[10px] font-extrabold tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    ${step.badge}
                </span>
                <button type="button" onclick="closeInteractiveTour()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br ${step.color} text-white flex items-center justify-center text-xl shadow-lg shrink-0">
                    <i class="fa-solid ${step.icon}"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-white leading-tight">${step.title}</h3>
                </div>
            </div>

            <p class="text-xs text-slate-300 leading-relaxed mb-6 bg-slate-950/50 p-4 rounded-2xl border border-slate-800/80">
                ${step.content}
            </p>

            <!-- Step Dots Progress Indicator -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-800">
                <div class="flex items-center gap-1.5">
                    ${tourSteps.map((_, idx) => `
                        <span class="w-2.5 h-2.5 rounded-full transition-all duration-300 ${idx === currentTourStep ? 'bg-indigo-500 w-6' : 'bg-slate-700'}"></span>
                    `).join('')}
                </div>

                <div class="flex items-center gap-2">
                    ${!isFirst ? `
                        <button type="button" onclick="prevTourStep()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-all">
                            Kembali
                        </button>
                    ` : ''}
                    ${!isLast ? `
                        <button type="button" onclick="nextTourStep()" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-1.5">
                            <span>Lanjut</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </button>
                    ` : `
                        <button type="button" onclick="finishInteractiveTour()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-check text-[10px]"></i> <span>Mulai Portal</span>
                        </button>
                    `}
                </div>
            </div>
        </div>
    `;
}

function startInteractiveTour() {
    currentTourStep = 0;
    renderTourModal();
    const modal = document.getElementById('interactive-tour-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function nextTourStep() {
    if (currentTourStep < tourSteps.length - 1) {
        currentTourStep++;
        renderTourModal();
    }
}

function prevTourStep() {
    if (currentTourStep > 0) {
        currentTourStep--;
        renderTourModal();
    }
}

function closeInteractiveTour() {
    const modal = document.getElementById('interactive-tour-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function finishInteractiveTour() {
    localStorage.setItem('has_seen_school_tour', 'true');
    closeInteractiveTour();
}

// Auto launch tour only on first visit ever
document.addEventListener('DOMContentLoaded', () => {
    if (!localStorage.getItem('has_seen_school_tour')) {
        setTimeout(() => {
            startInteractiveTour();
        }, 1000);
    }
});
