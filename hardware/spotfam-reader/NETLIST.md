# Netzliste SpotFam Reader Rev A

Generiert aus `tools/design.py`. Nicht von Hand bearbeiten.

| Netz | Anschluesse |
|---|---|
| `+3V3` | U1.2 (3V3), U2.2 (VOUT), R3.1, R4.1, C2.1, C3.1, C4.1, C6.1, R7.1, R8.1, R6.1, J2.2 (VCC 3V3), JP1.3 (B), JP2.1 (A), J4.2 (VCC 3V3), J7.1 (3V3) |
| `+5V` | J1.A4 (VBUS), J1.A9 (VBUS), J1.B4 (VBUS), J1.B9 (VBUS), U2.3 (VIN), U3.5 (VBUS), C1.1 |
| `BOOT` | U1.27 (IO0/BOOT), R4.2, SW6.1 |
| `BTN_NEXT` | U1.4 (IO4), SW1.1, J6.2 (NEXT) |
| `BTN_PREV` | U1.5 (IO5), SW2.1, J6.3 (PREV) |
| `BTN_VOLDN` | U1.7 (IO7), SW4.1, J6.5 (VOL-) |
| `BTN_VOLUP` | U1.6 (IO6), SW3.1, J6.4 (VOL+) |
| `CC1` | J1.A5 (CC1), R1.1 |
| `CC2` | J1.B5 (CC2), R2.1 |
| `EN` | U1.3 (EN), R3.2, C5.1, SW5.1 |
| `EXP_IO1` | U1.39 (IO1), J7.3 (IO1) |
| `EXP_IO18` | U1.11 (IO18), J7.5 (IO18) |
| `EXP_IO2` | U1.38 (IO2), J7.4 (IO2) |
| `EXP_IO38` | U1.31 (IO38), J7.6 (IO38) |
| `GND` | U1.1 (GND), U1.40 (GND), U1.41 (GND), J1.A1 (GND), J1.A12 (GND), J1.B1 (GND), J1.B12 (GND), J1.S1 (SHIELD), U2.1 (GND), U3.2 (GND), R1.2, R2.2, C5.2, C1.2, C2.2, C3.2, C4.2, C6.2, D1.1 (K), D2.1 (K), SW1.2, SW2.2, SW3.2, SW4.2, SW5.2, SW6.2, J2.1 (GND), JP1.1 (A), JP2.3 (B), J4.1 (GND), J5.1 (GND), J6.1 (GND), J7.2 (GND) |
| `I2C_SCL` | U1.17 (IO9), R8.2, J2.4 (SCL), J3.3 (SCL) |
| `I2C_SDA` | U1.12 (IO8), R7.2, J2.3 (SDA), J3.4 (SDA) |
| `LED_PWR_A` | R6.2, D2.2 (A) |
| `LED_STATUS` | U1.8 (IO15), R5.1 |
| `LED_STAT_A` | R5.2, D1.2 (A) |
| `OLED_P1` | J3.1 (P1 (GND/VCC)), JP1.2 (C) |
| `OLED_P2` | J3.2 (P2 (VCC/GND)), JP2.2 (C) |
| `PN532_IRQ` | U1.9 (IO16), J2.5 (IRQ) |
| `PN532_RST` | U1.10 (IO17), J2.6 (RSTO) |
| `SPI_CS` | U1.18 (IO10/FSPICS0), J4.6 (CS) |
| `SPI_MISO` | U1.21 (IO13/FSPIQ), J4.5 (MISO) |
| `SPI_MOSI` | U1.19 (IO11/FSPID), J4.4 (MOSI/SDA) |
| `SPI_SCK` | U1.20 (IO12/FSPICLK), J4.3 (SCK) |
| `TFT_BL` | U1.24 (IO47), J4.9 (BL) |
| `TFT_DC` | U1.22 (IO14), J4.7 (DC) |
| `TFT_RST` | U1.23 (IO21), J4.8 (RST) |
| `UART_RX` | U1.36 (RXD0/IO44), J5.3 (RX (ESP)) |
| `UART_TX` | U1.37 (TXD0/IO43), J5.2 (TX (ESP)) |
| `USB_DN` | U1.13 (IO19/USB_D-), J1.A7 (D-), J1.B7 (D-), U3.1 (IO1), U3.6 (IO1) |
| `USB_DP` | U1.14 (IO20/USB_D+), J1.A6 (D+), J1.B6 (D+), U3.3 (IO2), U3.4 (IO2) |
