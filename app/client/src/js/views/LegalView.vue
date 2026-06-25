<template>
  <div class="legal-view">
    <header class="setup-header">
      <button class="btn btn--ghost btn--sm setup-header__back" @click="router.back()">
        ← Zurück
      </button>
      <h2 class="setup-header__title">Rechtliches</h2>
      <p class="setup-header__sub">Impressum & Datenschutz</p>
    </header>

    <section class="legal-section">
      <h3 class="legal-section__title">Impressum</h3>
      <p class="legal-section__notice">Angaben gemäß § 5 TMG</p>

      <div class="legal-block">
        <p><strong>Betreiber</strong></p>
        <p>
          [Dein Name / Firmenname]<br>
          [Straße und Hausnummer]<br>
          [PLZ Ort]
        </p>
      </div>

      <div class="legal-block">
        <p><strong>Kontakt</strong></p>
        <p>
          E-Mail: [deine@email.de]
        </p>
      </div>

      <div class="legal-block">
        <p><strong>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</strong></p>
        <p>
          [Dein Name]<br>
          [Anschrift wie oben]
        </p>
      </div>
    </section>

    <hr class="legal-divider" />

    <section class="legal-section">
      <h3 class="legal-section__title">Datenschutzerklärung</h3>

      <div class="legal-block">
        <p><strong>1. Verantwortlicher</strong></p>
        <p>
          Verantwortlicher im Sinne der DSGVO ist:<br>
          [Dein Name / Firmenname], [Adresse], [E-Mail]
        </p>
      </div>

      <div class="legal-block">
        <p><strong>2. Erhobene Daten</strong></p>
        <p>
          DarePile speichert Spielsitzungen (Spielername, Kartenwahl, Spielverlauf) temporär
          auf dem Server, um eine laufende Spielrunde zu ermöglichen. Es werden keine
          personenbezogenen Daten dauerhaft gespeichert oder an Dritte weitergegeben.
        </p>
      </div>

      <div class="legal-block">
        <p><strong>3. Hosting</strong></p>
        <p>
          Diese Website wird bei [Hosting-Anbieter, z.&nbsp;B. Hetzner] gehostet.
          Die Server befinden sich in [Land, z.&nbsp;B. Deutschland / der EU].
          Der Hoster verarbeitet Verbindungsdaten (IP-Adresse, Zeitstempel) im Rahmen
          seiner Leistungserbringung.
        </p>
      </div>

      <div class="legal-block">
        <p><strong>4. Cookies & lokale Speicherung</strong></p>
        <p>
          Die App nutzt <code>localStorage</code> im Browser, um den Spielstand
          (Spielhash) zwischen Sitzungen zu merken. Es werden keine Tracking-Cookies
          gesetzt.
        </p>
      </div>

      <div class="legal-block">
        <p><strong>5. Deine Rechte</strong></p>
        <p>
          Du hast das Recht auf Auskunft, Berichtigung, Löschung und Einschränkung
          der Verarbeitung deiner Daten. Wende dich dazu an: [deine@email.de]
        </p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
</script>
