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
      value: null // °C
    },
    targetTemp: {
      id: '0_userdata.0.Soll-Temperatur_Wohnzimmer',
      value: null // °C
    },
    humidity: {
      id: 'sonoff.0.IRWohnzimmer.HTU21_Humidity',
      value: null // %
    },
    dewPoint: {
      id: 'sonoff.0.IRWohnzimmer.HTU21_DewPoint',
      value: null // °C
    },
    // Thermostat-Relais
    thermostat: {
      id: 'mqtt.0.HeizungThermostatWZ1.Relay.State',
      state: null // 0 = aus, 1 = ein
    },
    // Infrarot
    ir: {
      id: 'sonoff.0.IRWohnzimmer.POWER',
      state: null // true/false (On/Off)
    },
    // Energieverbrauch
    energy: {
      today: { id: 'sonoff.0.PowerWZ.ENERGY_Today', value: null },
      month: { id: 'sonoff.0.PowerWZ.ENERGY_Today', value: null } // Monat wird aus Tageswerten aggregiert
    },
    // Klimaanlage
    climate: {
      prefix: 'midea.0.145135534992585.control.',
      powerState: { id: 'midea.0.145135534992585.control.powerState', state: null },
      turboMode: { id: 'midea.0.145135534992585.control.turboMode', state: null },
      ecoMode: { id: 'midea.0.145135534992585.control.ecoMode', state: null },
      targetTemperature: { id: 'midea.0.145135534992585.control.targetTemperature', value: null },
      fanSpeed: { id: 'midea.0.145135534992585.control.fanSpeed', value: null },
      operationalMode: { id: 'midea.0.145135534992585.control.operationalMode', value: null },
      swingMode: { id: 'midea.0.145135534992585.control.swingMode', value: null }
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
      value: null // °C
    },
    targetTemp: {
      id: '0_userdata.0.Soll-Temperatur_Schlafzimmer',
      value: null // °C
    },
    humidity: {
      id: 'mqtt.0.Innentemperatur_1.BMP280.Humidity',
      value: null // %
    },
    // Thermostat-Relais
    thermostat: {
      id: 'mqtt.0.HeizungThermostatWZ1.Relay.State',
      state: null // 0 = aus, 1 = ein
    },
    // Klimaanlage
    climate: {
      prefix: 'midea.0.19791209303536.control.',
      powerState: { id: 'midea.0.19791209303536.control.powerState', state: null },
      turboMode: { id: 'midea.0.19791209303536.control.turboMode', state: null },
      ecoMode: { id: 'midea.0.19791209303536.control.ecoMode', state: null },
      targetTemperature: { id: 'midea.0.19791209303536.control.targetTemperature', value: null },
      fanSpeed: { id: 'midea.0.19791209303536.control.fanSpeed', value: null },
      operationalMode: { id: 'midea.0.19791209303536.control.operationalMode', value: null },
      swingMode: { id: 'midea.0.19791209303536.control.swingMode', value: null }
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
  },
  vorraum: {
    lights: [
      { id: 'sonoff.0.VorzimmerLicht.POWER', label: 'Licht', state: null }
    ]
  },
  esszimmer: {
    climate: {
      prefix: 'midea.0.DEINE_ESSZIMMER_ID.control.',
      powerState: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.powerState', state: null },
      turboMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.turboMode', state: null },
      ecoMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.ecoMode', state: null },
      targetTemperature: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.targetTemperature', value: null },
      fanSpeed: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.fanSpeed', value: null },
      operationalMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.operationalMode', value: null },
      swingMode: { id: 'midea.0.DEINE_ESSZIMMER_ID.control.swingMode', value: null }
    }
  },
  warmwasser: {
    energy: {
      today: { id: 'alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Energy', value: null },
      month: { id: 'alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Energy', value: null }
    },
    power: { id: 'alias.0.shelly.0.shellyplus1pm80646fe2dfd4.Relay0.Switch', state: null },
    temperature: { id: 'mqtt.0.WarmWasserSteuerung.DS18B20.WarmwasserOben', value: null },
    override: { id: '0_userdata.0.OverrideWarmwasser', state: null },
    // Temperatur-Log wird per API geladen: /iobroker/api/energy-warmwasser.php?days=7
  },
  photovoltaik: {
    produktion: { id: '0_userdata.0.Photovoltaik.Huawei.Inverter1.Accumulated_energy_yield', value: null },
    einspeisung: { id: '0_userdata.0.Photovoltaik.Huawei.Meter.Positive_active_electricity', value: null },
    verbrauch: { id: '0_userdata.0.Photovoltaik.Huawei.Meter.Reverse_active_power', value: null },
    // Wochenwerte werden dynamisch aus sourceanalytix geladen
    verbrauchWoche: { base: 'sourceanalytix.0.0_userdata__0__Huawei__Meter__Active_Power.2023.consumed.weeks.' },
    einspeisungWoche: { base: 'sourceanalytix.0.0_userdata__0__Huawei__Inverter__Daily_Energy_Yield.2023.earnings.weeks.' },
    activePower: { id: '0_userdata.0.Photovoltaik.Huawei.Meter.Active_power', value: null } // Live-Wert in W
  },
}