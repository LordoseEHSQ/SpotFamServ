import { describe, it, expect } from 'vitest';
import { chipFamily, evaluateChipMatch } from './chipMatch';

describe('chipFamily', () => {
  it('leitet die klassische ESP32-Familie aus voller Bezeichnung ab', () => {
    expect(chipFamily('ESP32-D0WD-V3')).toBe('esp32');
    expect(chipFamily('ESP32-D0WDR2-V3')).toBe('esp32');
  });

  it('akzeptiert bereits berechnete Familien-Strings', () => {
    expect(chipFamily('ESP32')).toBe('esp32');
    expect(chipFamily('esp32')).toBe('esp32');
  });

  it('unterscheidet S-/C-Varianten vom klassischen ESP32', () => {
    expect(chipFamily('ESP32-S3')).toBe('esp32s3');
    expect(chipFamily('ESP32-S2')).toBe('esp32s2');
    expect(chipFamily('ESP32-C3')).toBe('esp32c3');
    expect(chipFamily('ESP8266')).toBe('esp8266');
  });

  it('ignoriert Gross-/Kleinschreibung und Leerzeichen', () => {
    expect(chipFamily('  esp32-d0wd-v3  ')).toBe('esp32');
  });

  it('gibt null fuer unbekannte oder leere Eingaben zurueck', () => {
    expect(chipFamily('')).toBeNull();
    expect(chipFamily(null)).toBeNull();
    expect(chipFamily(undefined)).toBeNull();
    expect(chipFamily('unknown')).toBeNull();
    expect(chipFamily('STM32F4')).toBeNull();
  });
});

describe('evaluateChipMatch', () => {
  const device = { chip: 'esp32', chipDescription: 'ESP32-D0WD-V3' };

  it('match: CLI-Artefakt mit voller Bezeichnung (frueherer Falsch-Positiv-Block)', () => {
    expect(evaluateChipMatch('ESP32-D0WD-V3', device)).toBe('match');
  });

  it('match: Upload-UI-Artefakt mit grober Familie', () => {
    expect(evaluateChipMatch('ESP32', device)).toBe('match');
  });

  it('mismatch: andere Familie wird blockiert', () => {
    expect(evaluateChipMatch('ESP32-S3', device)).toBe('mismatch');
    expect(evaluateChipMatch('ESP8266', device)).toBe('mismatch');
  });

  it('unknown: nicht klassifizierbarer expectedChip blockiert nicht', () => {
    expect(evaluateChipMatch('STM32', device)).toBe('unknown');
    expect(evaluateChipMatch('', device)).toBe('unknown');
  });

  it('unknown: Geraet ohne erkennbare Familie blockiert nicht', () => {
    expect(evaluateChipMatch('ESP32', { chip: 'unknown', chipDescription: '' })).toBe('unknown');
  });

  it('faellt auf chipDescription zurueck, wenn chip-Familie leer ist', () => {
    expect(evaluateChipMatch('ESP32', { chip: '', chipDescription: 'ESP32-D0WD-V3' })).toBe('match');
  });
});
