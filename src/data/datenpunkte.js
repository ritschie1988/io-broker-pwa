// src/data/datenpunkte.js
// Objektmodell für Räume mit Sensoren und Aktoren für die Heizungssteuerung
// Hier können weitere Räume und Geräte ergänzt werden

export const rooms = {
  wohnzimmer: {
    lights: [
      { id: 'sonoff.0.WohnzimmerLicht.POWER', label: 'Hauptlicht', state: null },
      { id: '0_userdata.0.WohnzimmerAmbientePower', label: 'Fernsehlicht', state: null }
    ],
    // Temperatur-Sensoren und Zielwerte
    temperature: {
      id: 'tuya.0.bf2557b234ab753959fp8n.1',
      label: 'Raumtemperatur',
      value: null // °C
    },
    targetTemp: {
      id: '0_userdata.0.Soll-Temperatur_Wohnzimmer',
      label: 'Solltemperatur',
      value: null // °C
    },
    humidity: {
      id: 'sonoff.0.IRWohnzimmer.HTU21_Humidity',
      label: 'Luftfeuchtigkeit',
      value: null // %
    },
    dewPoint: {
      id: 'sonoff.0.IRWohnzimmer.HTU21_DewPoint',
      label: 'Taupunkt',
      value: null // °C
    },
    // Thermostat-Relais
    thermostat: {
      id: 'mqtt.0.HeizungThermostatWZ1.Relay.State',
      label: 'Heizungsthermostat',
      state: null // 0 = aus, 1 = ein
    },
    // Infrarot
    ir: {
      id: 'sonoff.0.IRWohnzimmer.POWER',
      label: 'Infrarot',
      state: null // true/false (On/Off)
    },
    // Energieverbrauch
    energy: {
      today: { id: 'sonoff.0.PowerWZ.ENERGY_Today', label: 'Energie heute', value: null },
      month: { id: 'sonoff.0.PowerWZ.ENERGY_Today', label: 'Energie Monat', value: null } // Monat wird aus Tageswerten aggregiert
    },
    // Klimaanlage
    climate: {
      prefix: 'midea.0.145135534992585.control.',
      powerState: { id: 'midea.0.145135534992585.control.powerState', label: 'Klimaanlage Power', state: null },
      turboMode: { id: 'midea.0.145135534992585.control.turboMode', label: 'Turbo-Modus', state: null },
      ecoMode: { id: 'midea.0.145135534992585.control.ecoMode', label: 'Eco-Modus', state: null },
      targetTemperature: { id: 'midea.0.145135534992585.control.targetTemperature', label: 'Klimaanlage Solltemperatur', value: null },
      fanSpeed: { id: 'midea.0.145135534992585.control.fanSpeed', label: 'Lüftergeschwindigkeit', value: null },
      operationalMode: { id: 'midea.0.145135534992585.control.operationalMode', label: 'Betriebsmodus', value: null },
      swingMode: { id: 'midea.0.145135534992585.control.swingMode', label: 'Swing-Modus', value: null }
    }
  },
  schlafzimmer: {
    lights: [
      { id: 'sonoff.0.SZLightMain.POWER', label: 'Hauptlicht', state: null },
      { id: 'sonoff.0.SchlafzimmerNachtlicht.ENERGY_Power', label: 'Nachtlicht', state: null }
    ],
    // Temperatur-Sensoren und Zielwerte
    temperature: {
      id: 'mqtt.0.Innentemperatur_1.BMP280.Temperature',
      label: 'Raumtemperatur',
      value: null // °C
    },
    targetTemp: {
      id: '0_userdata.0.Soll-Temperatur_Schlafzimmer',
      label: 'Solltemperatur',
      value: null // °C
    },
    humidity: {
      id: 'mqtt.0.Innentemperatur_1.BMP280.Humidity',
      label: 'Luftfeuchtigkeit',
      value: null // %
    },
    // Thermostat-Relais
    thermostat: {
      id: 'mqtt.0.HeizungThermostatWZ1.Relay.State',
      label: 'Heizungsthermostat',
      state: null // 0 = aus, 1 = ein
    },
    // Klimaanlage
    climate: {
      prefix: 'midea.0.19791209303536.control.',
      powerState: { id: 'midea.0.19791209303536.control.powerState', label: 'Klimaanlage Power', state: null },
      turboMode: { id: 'midea.0.19791209303536.control.turboMode', label: 'Turbo-Modus', state: null },
      ecoMode: { id: 'midea.0.19791209303536.control.ecoMode', label: 'Eco-Modus', state: null },
      targetTemperature: { id: 'midea.0.19791209303536.control.targetTemperature', label: 'Klimaanlage Solltemperatur', value: null },
      fanSpeed: { id: 'midea.0.19791209303536.control.fanSpeed', label: 'Lüftergeschwindigkeit', value: null },
      operationalMode: { id: 'midea.0.19791209303536.control.operationalMode', label: 'Betriebsmodus', value: null },
      swingMode: { id: 'midea.0.19791209303536.control.swingMode', label: 'Swing-Modus', value: null }
    }
  },
  bad: {
    energy: {
      today: { id: 'sonoff.0.PowerBad.ENERGY_Today', value: null },
      month: { id: 'sonoff.0.PowerBad.ENERGY_Today', value: null }
    }
  },
  kueche: {
    lights: [
      { id: 'sonoff.0.KuecheLicht.POWER', label: 'Hauptlicht', state: null },
      { id: 'alias.0.shelly.0.shellyplus1pm80646FE2A22C.Relay0.Switch', label: 'Kochlicht 1', state: null },
      { id: 'alias.0.shelly.0.shellyplus178ee4ccd3f64.Relay0.Switch', label: 'Kochlicht 2', state: null }
    ]
    // Temperatur und weitere Geräte können analog ergänzt werden
  },
  vorraum: {
    lights: [
      { id: 'sonoff.0.VorzimmerLicht.POWER', label: 'Licht', state: null }
    ]
    // Temperatur und weitere Geräte können analog ergänzt werden
  },
  esszimmer: {
    climate: {
      prefix: 'midea.0.DEINE_ESSZIMMER_ID.control.',
      powerState: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.powerState', label: 'Klimaanlage Power', state: null },
      turboMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.turboMode', label: 'Turbo-Modus', state: null },
      ecoMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.ecoMode', label: 'Eco-Modus', state: null },
      targetTemperature: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.targetTemperature', label: 'Klimaanlage Solltemperatur', value: null },
      fanSpeed: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.fanSpeed', label: 'Lüftergeschwindigkeit', value: null },
      operationalMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.operationalMode', label: 'Betriebsmodus', value: null },
      swingMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.swingMode', label: 'Swing-Modus', value: null }
    }
  },
  warmwasser: {
    energy: {
      today: { id: 'alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Energy', label: 'Energie heute', value: null },
      month: { id: 'alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Energy', label: 'Energie Monat', value: null }
    },
    power: { id: 'alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Switch', label: 'Warmwasser Power', state: null },
    temperature: { id: 'mqtt.0.WarmWasserSteuerung.DS18B20.WarmwasserOben', label: 'Warmwasser Temperatur', value: null },
    override: { id: '0_userdata.0.OverrideWarmwasser', label: 'Warmwasser Override', state: null },
    // Temperatur-Log wird per API geladen: /iobroker/api/energy-warmwasser.php?days=7
  },
  photovoltaik: {
    produktion: { id: '0_userdata.0.Photovoltaik.Huawei.Inverter1.Accumulated_energy_yield', label: 'PV Produktion', value: null },
    einspeisung: { id: '0_userdata.0.Photovoltaik.Huawei.Meter.Positive_active_electricity', label: 'PV Einspeisung', value: null },
    verbrauch: { id: '0_userdata.0.Photovoltaik.Huawei.Meter.Reverse_active_power', label: 'PV Verbrauch', value: null },
    // Wochenwerte werden dynamisch aus sourceanalytix geladen
    verbrauchWoche: { base: 'sourceanalytix.0.0_userdata__0__Huawei__Meter__Active_Power.2023.consumed.weeks.', label: 'PV Verbrauch Woche' },
    einspeisungWoche: { base: 'sourceanalytix.0.0_userdata__0__Huawei__Inverter__Daily_Energy_Yield.2023.earnings.weeks.', label: 'PV Einspeisung Woche' },
    activePower: { id: '0_userdata.0.Photovoltaik.Huawei.Meter.Active_power', label: 'PV Leistung', value: null } // Live-Wert in W
  },
}