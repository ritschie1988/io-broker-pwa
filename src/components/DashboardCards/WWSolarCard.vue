<template>
  <div class="wwsolar-card">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="34.5 4 526.0859 276" width="526.086px" height="276px" xmlns:bx="https://boxy-svg.com" preserveAspectRatio="none">
      <defs>
        <linearGradient id="svg_11" x1="0" y1="0" x2="0" y2="1" spreadMethod="pad">
          <stop stop-color="#bf0000" stop-opacity="0.99609" offset="0"/>
          <stop stop-color="#0000ff" stop-opacity="0.99609" offset="1"/>
          <stop stop-color="#0000ff" stop-opacity="0.99609" offset="1"/>
        </linearGradient>
        <linearGradient id="gradient-1-0" href="#gradient-1" gradientUnits="userSpaceOnUse" x1="328.5" y1="146" x2="328.5" y2="278"/>
        <linearGradient id="gradient-1" bx:pinned="true">
          <stop offset="0" style="stop-color: rgba(191, 0, 0, 0.996);"/>
          <stop offset="1" style="stop-color: rgba(0, 0, 255, 0.996);"/>
          <stop offset="1" style="stop-color: rgba(0, 0, 255, 0.996);"/>
        </linearGradient>
        <linearGradient gradientUnits="userSpaceOnUse" x1="322.266" y1="252" x2="322.266" y2="260.935" id="gradient-1-1" href="#gradient-1"/>
        <linearGradient id="gradient-1-2" href="#gradient-1" gradientUnits="userSpaceOnUse" x1="322.266" y1="252" x2="322.266" y2="260.935"/>
        <linearGradient spreadMethod="pad" y2="0" x2="0" y1="0" x1="1" id="svg_33">
          <stop offset="0.1953" stop-opacity="0.99219" stop-color="#00007f"/>
          <stop offset="1" stop-opacity="0.99609" stop-color="#ff0000"/>
        </linearGradient>
        <linearGradient id="gradient-2-0" href="#gradient-2" gradientUnits="userSpaceOnUse" x1="104.201" y1="71.371" x2="104.201" y2="79.129" spreadMethod="pad"/>
        <linearGradient id="gradient-2" bx:pinned="true">
          <stop offset="0.1953" style="stop-color: rgba(0, 0, 127, 0.992);"/>
          <stop offset="1" style="stop-color: rgba(255, 0, 0, 0.996);"/>
        </linearGradient>
      </defs>
      <g id="object-0">
        <title>Layer 1</title>
        <path fill="url(#svg_11)" opacity="undefined" d="m284.52273,147.06451l88.97727,-1.06452l0,132.00001l-90,0l0,-66l1.02273,-64.93549z" id="svg_1" fill-opacity="0.76" style="stroke: url(#gradient-1-0); stroke-width: 10px; stroke-linejoin: round; stroke-linecap: round;"/>
        <path stroke="#ff0000" fill="none" fill-opacity="0.76" opacity="undefined" d="M 153.468 240.88 L 331.059 240.532" id="svg_4" stroke-width="4" style="stroke-linecap: round;"/>
        <line fill="none" fill-opacity="0.76" x1="315.059" y1="250.818" x2="331.5" y2="241" id="svg_5" stroke-width="3" style="stroke: rgba(255, 0, 0, 0.996); stroke-linecap: round;"/>
        <path opacity="undefined" d="M 314.968 250.909 L 332.058 260.935" id="svg_7" stroke-width="3" style="fill-rule: nonzero; fill: url(#gradient-1-1); stroke: url(#gradient-1-2); stroke-linecap: round;"/>
        <line fill="none" x1="331.527" y1="261.624" x2="53.5" y2="261" id="svg_8" stroke="#00007f" stroke-width="4" style="stroke-linecap: round;"/>
        <path transform="rotate(135 104.201 76.3108) translate(30.8792 71.3713) scale(1 1.27346) translate(-30.8792 -71.3713)" fill="url(#svg_33)" opacity="undefined" d="m30.87925,71.3714l146.64345,0l0,7.75736l-146.64345,0l0,-7.75736z" id="svg_9" style="stroke-linejoin: round; stroke: url(#gradient-2-0); stroke-width: 3.51887px; paint-order: markers;"/>
        <line fill="none" x1="54.5" y1="263" x2="53.759" y2="125.073" id="svg_13" stroke="#00007f" stroke-width="4"/>
        <ellipse stroke-width="0" fill="#0000bf" cx="56.5" cy="259" id="svg_14" rx="22" ry="21" stroke="url(#gradbox_stroke)"/>
        <path stroke="url(#gradbox_stroke)" id="svg_26" d="m38.9175,268.01313l17.00001,-27.00001l17,27.00001l-17,0l-8.50001,0l-4.25,0l-2.125,0l-2.125,0z" :fill="sensordaten.relay1 == 1 ? '#00bf00' : '#bf0000'" style=""/>
        <path stroke="#ff0000" fill="#ffff00" d="M 178.695 4 L 227.998 4 C 234.457 4 239.693 9.82 239.693 17 C 239.693 24.18 234.457 30 227.998 30 L 178.695 30 C 172.236 30 167 24.18 167 17 C 167 9.82 172.236 4 178.695 4 Z" id="svg_19" style=""/>
        <text fill="#000000" stroke="#ff0000" x="173.5" y="21.5" id="svg_20" stroke-width="0" font-size="13" font-family="Noto Sans JP" text-anchor="start" font-style="normal" style="white-space: pre;">S1: {{ sensordaten.sensor1 }}°C</text>
        <path stroke="#ff0000" fill="#ffff00" d="m394.31672,155.69061l49.51157,0l0,0c6.48616,0 11.74421,5.37259 11.74421,12c0,6.62741 -5.25806,12 -11.74421,12l-49.51157,0l0,0c-6.48615,0 -11.74421,-5.37258 -11.74421,-12c0,-6.62742 5.25806,-12 11.74421,-12z" id="svg_15"/>
        <text fill="#000000" stroke="#ff0000" x="390" y="172.19061" id="svg_22" stroke-width="0" font-size="13" font-family="Noto Sans JP" text-anchor="start" font-style="normal" style="white-space: pre;">S2: {{ sensordaten.sensor2 }}°C</text>
        <path stroke="#ff0000" fill="#ffff00" d="m394.97759,204.69061l50.18981,0l0,0c6.57501,0 11.90509,5.37258 11.90509,12c0,6.62741 -5.33009,12 -11.90509,12l-50.18981,0l0,0c-6.575,0 -11.90509,-5.37259 -11.90509,-12c0,-6.62742 5.3301,-12 11.90509,-12z" id="svg_16"/>
        <text fill="#000000" stroke="#ff0000" x="389.5" y="221.19061" id="svg_23" stroke-width="0" font-size="13" font-family="Noto Sans JP" text-anchor="start" font-style="normal" style="white-space: pre;">S3: {{ sensordaten.sensor3 }}°C</text>
        <path stroke="#ff0000" fill="#ffff00" d="m394.81671,253.69061l49.51157,0l0,0c6.48616,0 11.74421,5.37258 11.74421,12c0,6.62741 -5.25806,12 -11.74421,12l-49.51157,0l0,0c-6.48615,0 -11.74421,-5.37259 -11.74421,-12c0,-6.62742 5.25807,-12 11.74421,-12z" id="svg_18"/>
        <text fill="#000000" stroke="#ff0000" x="389.5" y="270.19061" id="svg_24" stroke-width="0" font-size="13" font-family="Noto Sans JP" text-anchor="start" font-style="normal" style="white-space: pre;">S4: {{ sensordaten.sensor4 }}°C</text>
        <path id="svg_32" d="M 154.205 25.409 L 152.873 240" opacity="undefined" stroke-width="4" stroke="#ff0000" fill="#ff0000" style="stroke-linecap: round;"/>
        <text font-weight="bold" text-anchor="start" font-family="Noto Sans JP" font-size="13" stroke-width="0" id="svg_2" y="138" x="304.5" fill-opacity="0.76" stroke="#ff0000" fill="#000000" style="white-space: pre;">Speicher</text>
      </g>
    </svg>
    <div class="zeit" v-if="sensordaten.zeit">
      Letztes Update: {{ sensordaten.zeit }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'WWSolarCard',
  data() {
    return {
      sensordaten: {}
    }
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData() {
      try {
        const res = await fetch('/iobroker/api/get_latest_sensordaten.php');
        this.sensordaten = await res.json();
      } catch (e) {
        this.sensordaten = { sensor1: '-', sensor2: '-', sensor3: '-', sensor4: '-', zeit: 'Fehler beim Laden' };
      }
    }
  }
}
</script>

<style scoped>
.wwsolar-card {
  background: #f8f8f8;
  border-radius: 12px;
  box-shadow: 0 2px 8px #0001;
  padding: 16px;
  max-width: 480px;
  margin: 0 auto;
}
.zeit {
  margin-top: 10px;
  color: #888;
  font-size: 13px;
  text-align: right;
}
</style>
