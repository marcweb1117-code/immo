// public/js/inscription.js

document.addEventListener('DOMContentLoaded', () => {
    const typeCompteSelect = document.getElementById('type_compte');
    const proFields = document.getElementById('pro-fields');
    const passwordInput = document.getElementById('mot_de_passe');
    const strengthBar = document.getElementById('strength-bar');

    // 1. Gestion de l'affichage conditionnel pour les professionnels
    typeCompteSelect.addEventListener('change', (e) => {
        if (e.target.value === 'professionnel') {
            proFields.classList.remove('hidden');
            document.getElementById('nom_agence').setAttribute('required', 'required');
            document.getElementById('siren').setAttribute('required', 'required');
        } else {
            proFields.classList.add('hidden');
            document.getElementById('nom_agence').removeAttribute('required');
            document.getElementById('siren').removeAttribute('required');
        }
    });

    // 2. Indicateur visuel de la force du mot de passe
    passwordInput.addEventListener('input', (e) => {
        const val = e.target.value;
        let score = 0;

        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        // Mise à jour de la jauge
        switch(score) {
            case 0:
            case 1:
                strengthBar.style.width = '25%';
                strengthBar.style.backgroundColor = '#e53e3e'; // Rouge
                break;
            case 2:
                strengthBar.style.width = '50%';
                strengthBar.style.backgroundColor = '#dd6b20'; // Orange
                break;
            case 3:
                strengthBar.style.width = '75%';
                strengthBar.style.backgroundColor = '#d69e2e'; // Jaune
                break;
            case 4:
                strengthBar.style.width = '100%';
                strengthBar.style.backgroundColor = '#38a169'; // Vert
                break;
        }
    });
});