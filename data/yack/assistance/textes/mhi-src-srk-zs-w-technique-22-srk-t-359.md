---
document: mhi-src-srk-zs-w-technique-22-srk-t-359
title: Technical Manual Inverter Wall Mounted Type Residential Air-Conditioners (Split system, air to air heat pump type)
langue: en
pages_indexees: 45, 82-83, 85-86, 96, 98-99
---
[page 45]
’20 SRK DB-300D '22 • SRK-T-359 6. RANGE OF USAGE & LIMITATIONS

Model SRK20,25,35ZS-WF | SRK50ZS-WF SRK20,25,35ZS-WFB | SRK50ZS-WFB SRK20,25,35ZS-WFT | SRK50ZS-WFT Item Cooling operation : Approximately 18 to 32 ℃ DB Indoor return air temperature Heating operation : Approximately 10 to 30 ℃ DB (Upper, lower limits) (Refer to the selection chart) Cooling operation : Approximately -15 to 46 ℃ DB Outdoor air temperature Heating operation : Approximately -15 to 24 ℃ DB (Upper, lower limits) (Refer to the selection chart) Refrigerant line (one way) length | Max. 20m | Max. 25m Max. 10m | Max. 15m Vertical height difference between | (Outdoor unit is higher) | (Outdoor unit is higher) outdoor unit and indoor unit | Max. 10m | Max. 15m (Outdoor unit is lower) | (Outdoor unit is lower) Power source voltage | Rating ± 10% Voltage at starting | Min. 85% of rating Max. 4 times/h | Max. 7 times/h Frequency of ON-OFF cycle (Inching prevention 10 minutes) | (Inching prevention 5 minutes) ON and OFF interval | Min. 3 minutes

Selection chart Correct the cooling and heating capacity in accordance with the conditions as follows. The net cooling and heating capacity can be obtained in the following way. Net capacity = Capacity shown on specification × Correction factors as follows (1) Coefficient of cooling and heating capacity in relation to temperatures

[page 82]
'22 • SRK-T-359

(5) Self-diagnosis table

Indoor unit display panel Wired remote Description control | Cause | Display (flashing) condition RUN | TIMER | of trouble light | light | display

- Broken heat exchanger sensor When a heat exchanger sensor 1 wire disconnection is detected 1-time | Heat exchanger 1 wire, poor connector | while operation is stopped. (If a temperature of –28ºC or lower ON | , flash | sensor 1 error | connection | is detected for 15 seconds, it is judged that the wire is discon-

- Indoor unit PCB is faulty | nected.) (Not displayed during operation.)

- Broken room temperature | When a room temperature sensor wire disconnection is detected Room | while operation is stopped. (If a temperature of –45ºC or lower 2-time | sensor wire, poor connector ON | , | temperature | is detected for 15 seconds, it is judged that the wire is discoflash | connection sensor error | nnected.) (Not displayed during operation.)

- Indoor unit PCB is faulty

- Broken heat exchanger sensor When a heat exchanger sensor 2 wire disconnection is detected 3-time | Heat exchanger 2 wire, poor connector | while operation is stopped. (If a temperature of –28ºC or lower ON | , flash | sensor 2 error | connection | is detected for 15 seconds, it is judged that the wire is discon-

- Indoor unit PCB is faulty | nected.)(Not displayed during operation.) When conditions for turning the indoor unit’s fan motor on exist during air 6-time | Indoor fan | • Defective fan motor, poor | -1 ON | E 16 | -conditioner operation, an indoor unit fan motor speed of 300min flash | motor error | connector connection or lower is measured for 30 seconds or longer. (The air-conditioner stops.)

- Broken outdoor air temp. | –55ºC or lower is detected for 5 seconds continuously 3 times Outdoor air Keeps | 1-time | sensor wire, poor connector | within 40 minutes after initial detection of this anomalous E 38 | temperature flashing | flash | connection | temperature.Or –55ºC or higher is detected for within 20 sensor error

- Outdoor unit PCB is faulty | seconds after power ON. (The compressor is stopped.)

- Broken heat exchanger sensor | –55ºC or lower is detected for 5 seconds continuously 3 times Outdoor heat Keeps | 2-time | wire, poor connector | within 40 minutes after initial detection of this anomalous E 37 | exchanger flashing | flash | connection | temperature.Or –55ºC or higher is detected for within 20 sensor error

- Outdoor unit PCB is faulty | seconds after power ON. (The compressor is stopped.)

- Broken discharge pipe sensor –25ºC or lower is detected for 5 seconds continuously 3 times Keeps | 4-time | Discharge pipe | wire, poor connector E 39 | within 40 minutes after initial detection of this anomalous flashing | flash | sensor error | connection temperature.(The compressor is stopped.)

- Outdoor unit PCB is faulty

- Compressor locking, open phase 1-time | on compressor output, short | The compressor output current exceeds the set value during ON | E 42 | Current cut flash | circuit on power transistor , | compressor start. (The air-conditioner stops.) service valve is closed When there is an emergency stop caused by trouble in the 2-time | Trouble of | • Broken compressor wire ON | E 59 | outdoor unit, or the input current value is found to be lower flash | outdoor unit | • Compressor blockage than the set value.(The air-conditioner stops.)

- Overload operation 3-time | Current safe | When the compressor command speed is lower than the set ON | E 58 | • Overcharge flash | stop | value and the current safe has operated. (the compressor stops)

- Compressor locking 4-time | Power | When the power transistor is judged breakdown while ON | E 51 | • Broken power transistor flash | transistor error | compressor starts.(The compressor is stopped.)

- Gas shortage, defective | When the value of the discharge pipe sensor exceeds the set 5-time | Over heat of ON | E 36 | discharge pipe sensor, service | value.(The air-conditioner stops.) flash | compressor valve is closed When there is no signal between the indoor unit PCB and

- Defective power source, | outdoor unit PCB for 10 seconds or longer (when the power is 6-time | Error of signal ON | E 5 | Broken signal wire, defective | turned on), or when there is no signal for 7 minute 35 seconds flash | transmission indoor/outdoor unit PCB | or longer (during operation)(the compressor is stopped). When the outdoor unit’s fan motor speed continues for 30 7-time | Outdoor fan | • Defective fan motor, poor | seconds or longer at 75 min -1 or lower. (3 times) (The air ON | E 48 | motor error flash | connector connection -conditioner stops.)

- Overload operation, overcharge Cooling high Keeps | • Broken outdoor heat exchange When the value of the outdoor heat exchanger sensor exceeds ON | E 35 | pressure flashing | sensor wire | the set value. protecton

- Service valve is closed

- Defective compressor | If the compressor motor’s magnetic pole positions cannot be 2-time 2-time E 60 | Rotor lock | • Open phase on compressor | correctly detected when the compressor starts. flash | flash | • Defective outdoor unit PCB | (The air-conditioner stops.) Trouble of | • Defective wireless LAN 4-time | When normal data cannot be received from wireless LAN ON | , | wireless LAN | interface boards, poor connector flash | interface for two minutes continuously interface | connection 5-time | Active filter | When the wrong voltage connected for the power source. ON | E 47 | • Defective active filter flash | voltage error | When the outdoor unit PCB is faulty . Refrigeration 7-time | cycle system | • Service valve is closed. ON | E 57 | • Refrigerant is insufficient | When refrigeration cycle system protective control operates. flash | protective control Service valve | If the output current of inverter exceeds the specifications, it makes the compressor stopping. (In heating mode). 7-time 1-time | • Service valve (gas side) closed E 40 | (gas side) | After 3-minute delay, the compressor restarts, but if this anomaly occurs flash | flash | • Defective outdoor unit PCB closed opertion | 2 times within 20 minutes after the initial detection. The wired remote control wire Y is open. The wired remote Error of wired

- Broken wired remote control | control wires X and Y are reversely connected. Noise is penetrating , | , | E 1 | remote control wire, defective indoor unit PCB the wired remote control lines.The wired remote control or wiring indoor unit PCB is faulty. (The communications circuit is faulty.)

[page 83]
'09•SRK-DB-087D

'22 • SRK-T-359

(6) Service mode (Trouble mode access function)

(a) Explanation of terms Term | Explanation The service mode is the mode where service data are displayed by flashing of the display lights Service mode when the operations in item (b) below are performed with the indoor control. These are the contents of error displays and protective stops which occurred in the past in the airconditioner system. Error display contents and protective stop data from past anomalous Service data | operations of the air-conditioner system are saved in the indoor unit control’s non-volatile memory (memory which is not erased when the power goes off). There are two types of data, self-diagnosis data and stop data, described below. These are the data which display the reason why a stop occurred when an error display(selfdiagnosis display) occurred in an indoor unit. Data are recorded for up to 5 previous occurrences. Data which are older than the 5th previous occurrence are erased. Self-diagnosis data | In addition, data on the temperature of each sensor (room temperature, indoor heat exchanger, outdoor heat exchanger, outdoor air temperature, discharge pipe), remote control information (operation switching, fan speed switching) are recorded when trouble occurs, so more detailed information can be checked. These are the data which display the reason by a stop occurred when the air-conditioning system performed protective stops, etc. in the past. Even if stop data alone are generated, the system restarts automatically. (After executing the stop mode while the display is normal, the system restarts automatically.) Data for up to 10 previous occasions are stored. Data older than the 10th Stop data previous occasion are erased. ( Important) In cases where transient stop data only are generated, the air-conditioner system may still be normal. However, if the same protective stop occurs frequently (3 or more times), it could lead to customer complaints. (b) Service mode display procedure

- | wireless wireless

wireless

[page 85]
'09•SRK-DB-087D

'22 • SRK-T-359

(Example)

Wireless remote control setting Operation Fan speed Temperature | Displayed data mode | mode | setting 21ºC | Displays the reason for the stop (error code) the previous time an error was displayed. 22ºC | Displays the reason for the stop (error code) 2 times previous when an error was displayed. Cooling | MED | 23ºC | Displays the reason for the stop (error code) 3 times previous when an error was displayed. 24ºC | Displays the reason for the stop (error code) 4 times previous when an error was displayed. 25ºC | Displays the reason for the stop (error code) 5 times previous when an error was displayed.

(ii) Stop data Wireless remote control setting Operation | Fan speed Temperature | Displayed data mode | mode | setting 21ºC | Displays the reason for the stop (stop code) the previous time when the air-conditioner was stopped by protective stop control. 22ºC | Displays the reason for the stop (stop code) 2 times previous when the air-conditioner was stopped by protective stop control. 23ºC | Displays the reason for the stop (stop code) 3 times previous when the air-conditioner was stopped by protective stop control. 24ºC | Displays the reason for the stop (stop code) 4 times previous when the air-conditioner was stopped by protective stop control. 25ºC | Displays the reason for the stop (stop code) 5 times previous when the air-conditioner was stopped by protective stop control. Cooling | LO 26ºC | Displays the reason for the stop (stop code) 6 times previous when the air-conditioner was stopped by protective stop control. 27ºC | Displays the reason for the stop (stop code) 7 times previous when the air-conditioner was stopped by protective stop control. 28ºC | Displays the reason for the stop (stop code) 8 times previous when the air-conditioner was stopped by protective stop control. 29ºC | Displays the reason for the stop (stop code) 9 times previous when the air-conditioner was stopped by protective stop control. 30ºC | Displays the reason for the stop (stop code) 10 times previous when the air-conditioner was stopped by protective stop control.

[page 86]
'09•SRK-DB-087D

'22 • SRK-T-359

(c) Error code, stop code table

Number of flashes when in service mode | Stop code Error Auto RUN | TIMER | or | Error content | Cause | Occurrence conditions display recovery light | light | Error code (10's digit) (1's digit) OFF | 0 | Normal Error of wired remote control wiring | The wired remote control wire Y is open. The wired remote 1-time | (When wired remote control | Broken wired remote control wire. | control wires X and Y are reversely connected. 01 | was connected) OFF | flash | defective indoor unit PCB | Noise is penetrating the wired remote control lines. The (When wireless LAN interface | wired remote control or indoor unit PCB is faulty. was connected, refer to page 81.) Can not receive signals for 35 | Power source is faulty | When 35 seconds passes without 5-time | seconds | Power source cables and signal lines are improperly wired. communications signals from either the outdoor unit or flash (if communications have recovered) Indoor or outdoor unit PCB are faulty . | the indoor unit being detected correctly. Cooling overload operation. 5-time | When the outdoor heat exchanger sensor's value exceeds 35 | Cooling high pressure control | Outdoor unit fan speed drops. flash | the set value. | (5 times) Outdoor heat exchanger sensor is short circuit. Refrigerant is insufficient. 6-time | When the discharge pipe sensor's value exceeds the set 36 | Compressor overheat 110°C | Discharge pipe sensor is faulty. flash | value. | (2 times) Service valve is closed. –55°C or lower is detected for 5 seconds continuously 3 times Outdoor heat exchanger sensor wire is within 40 minutes after intial detection of this anomalous 7-time | Outdoor heat exchanger | disconnected. 37 | temperature. 3-time | flash | temperature sensor is abnormal | Connector connections are poor. | (3 times) Outdoor unit PCB is faulty . | Or–55°C higher is detected for 5 seconds continuously flash within 20 seconds after power ON. –55°C or lower is detected for 5 seconds continuously 3 times Outdoor air temperature sensor wire is within 40 minutes after intial detection of this anomalous 8-time | Outdoor air temperature sensor | disconnected. 38 | temperature. flash | is abnormal | Connector connections are poor. | (3 times) Outdoor unit PCB is faulty . | Or–55°C higher is detected for 5 seconds continuously within 20 seconds after power ON. Discharge pipe sensor wire is | –25°C or lower is detected for 5 seconds continuously 3 times Discharge pipe temperature 9-time | disconnected. 39 | sensor is abnormal | within 40 minutes after intial detection of this anomalous flash | Connector connections are poor. | (3 times) (anomalous stop) | temperature. Outdoor unit PCB is faulty . If the inverter output current value exceeds the setting value Service valve (gas side) | Service valve (gas side) closed OFF | 40 | within 80 seconds after the compressor ON in the heating closed operation | Outdoor unit PCB is faulty. | (2 times) mode, the compressor stops. Compressor lock. Compressor wiring short circuit. Compressor output is open phase. 2-time | Compressor start fails 42 times in succession and the 4-time | 42 | Current cut | Outdoor unit PCB is faulty . flash | reason for the final failure is current cut. | (2 times) flash | Service valve is closed. Electronic expansion valve is faulty. Compressor is faulty. 7-time | . | When the wrong voltage connected for the power source. 47 | Active filter voltage error | Defective active filter flash | When the outdoor unit PCB is faulty.

Outdoor fan motor is faulty. | -1 8-time | Outdoor unit's fan motor is | When a fan speed of 75 min or lower continues for 30 48 | Connector connections are poor. flash | abnormal | seconds or longer. | (3 times) Outdoor unit PCB is faulty . Short-circuit in the power 1-time | Outdoor unit PCB is faulty . | When it is judged that the power transistor was 51 | transistor (high side) flash | Power transistor is damaged. | damaged at the time the compressor started. Current cut circuit breakdown 7-time | Refrigeration cycle system | Service valve is closed. | When refrigeration cycle system protective control flash | protective control | Refrigerant is insufficient. | operates. | (3 times) Refrigerant is overcharge. 5-time | 8-time 58 | Current safe | Compressor lock. | When there is a current safe stop during operation. flash | flash Overload operation. Compressor wiring is disconnected. | When the current is 1A or less at the time the Compressor wiring is unconnection Power transistor is damaged. | compressor started. 9-time 59 | V oltage drop | Power source construction is defective. When the power source voltage drops during operation. flash Low speed protective control | Outdoor unit PCB is faulty . | When the compressor command speed is 1ower than 32 Compressor is faulty. | rps for 60 minutes.

Compressor is faulty. Compressor output is open phase. After the compressor starts, when the compressor stops OFF | 60 | Rotor lock | Electronic expansion valve is faulty. due to rotor lock. | (2 times) Overload operation. Outdoor unit PCB is faulty . 6-time Connection lines between the | When 10 seconds passes after the power is turned on flash | 1-time | Connection lines are faulty. 61 | indoor and outdoor units are | without communications signals from the indoor or flash | Indoor or outdoor unit PCB are faulty . faulty | outdoor unit being detected correctly.

When 7 minute 35 seconds passes without 2-time | Indoor or outdoor unit PCB are faulty . 62 | Serial transmission error | communications signals from either the outdoor unit or flash | Noise is causing faulty operation. the indoor unit being detected correctly. Indoor fan motor is faulty. | When the indoor unit's fan motor is detected to be running OFF | 80 | -1 Indoor fan motor is abnormal | Connector connections are poor. | at 300min or lower speed with the fan motor Indoor unit PCB is faulty . | in the ON condition while the air-conditioner is running. Indoor heat exchanger temperature Indoor heat exchanger sensor wire is | When a temperature of –28°C or lower is sensed 2-time 82 | sensor is abnormal | disconnected. | continuously for 40 minutes during heating operation. flash (anomalous stop) | Connector connections are poor. | (the compressor stops). 8-time | 4-time 84 | Anti-condensation control | High humidity condition. | Anti-condensation prevention control is operating. flash | flash 5-time | Indoor unit fan speed drops. | When the anti-frost control operates and the compressor 85 | Anti-frost control flash | Indoor heat exchanger sensor is broken wire. stops during cooling operation. Heating overload operation. 6-time | When high pressure control operates during heating 86 | Heating high pressure control | Indoor unit fan speed drops. flash | operation and the compressor stops. Indoor heat exchanger sensor is short circuit.

[page 96]
'22 • SRK-T-359

(8) Phenomenon observed after short-circuit, wire breakage on sensor (a) Indoor unit Operation | Phenomenon Sensor mode | Short-circuit | Disconnected wire Room temperature Cooling sensor | Heating

Heat exchanger | Cooling | Freezing cycle system protection trips and stops the compressor. temperature sensor | Heating Cooling Humidity sensor Heating Note (1) SRK35, 50 only.

Humidity sensor operation Failure mode | Control input circuit resding | Air-conditioning system operation

(b) Outdoor unit Operation | Phenomenon Sensor mode | Short-circuit | Disconnected wire Heat exchanger | Cooling | Compressor stop. temperature sensor Heating | Defrost operation is not performed. | Defrost operation is performed for 10 minutes at approx. 35 minutes. The compressor cannot pick up its speed owing to the current Cooling Ourdoor air | safe so that the designed capacity is not achieved. temperature sensor | The compressor cannot pick up its speed owing to the heating Heating | Defrost operation is performed for 10 minutes at approx. 35 minutes. overload protection so that the designed capacity is not achieved. Discharge pipe All modes temperature sensor

(9) Checking the indoor electrical equipment (a) Indoor unit PCB check procedure

Is there voltage between terminal | NO Inspect power source blocks | and | ? (AC 220/230/240 for outdoor unit. V) YES

YES Is the fuse burnt out? (3.15 A) | Replace fuse.

NO

Is the voltage between terminal | NO | Replace indoor unit blocks | and | oscillating between PCB. DC 0 and 20V? YES

Indoor electrical components are normal.

[page 98]
'22 • SRK-T-359

(10) How to make sure of wireless remote control

Simplified check method of wireless remote control It is normal if the signal transmission section of the wireless remote control emits a whitish light at each transmission on the monitor of digital camera.

(11) Inspection procedure for blown fuse on the indoor and outdoor unit PCB

Is the fuse blown on the indoor and | NO | Go to the inspection procedure for electric outdoor unit PCB? | equipment of indoor and outdoor units (See pages 95 to 100.)

Faulty part, other than | YES ( fan motor | ) YES | NO Is there any faulty part (fan motor, etc)? Replace the fuse, and check the operation. (See pages 95 to 100.)

Replace the faulty part and the | YES (Fan motor is faulty.) fuse. Test-run then the unit for | Is there any damage on the internal circuit checking. | of fan motor? Check the resistance value to judge. (See pages 95 to 100.)

Replace the fuse on the indoor and outdoor unit PCB. When the operation | NO Replace the fan motor, and check the operation. is checked without connecting the fan motor connector, does the fuse blow?

YES Replace the indoor and outdoor unit PCB and the fan motor. Check then the operation.

[page 99]
'22 • SRK-T-359

(12) Outdoor unit inspection points Models SRC20ZS-W, 25ZS-W, 35ZS-W SRC25ZS-W1, 35ZS-W1 SRC25ZS-W2, 35ZS-W2 ◆ Check point of outdoor unit WARNING - HIGH VOLTAGE High voltage is produced in the control box. Don't touch | ◆ Power source and serial signal inspection electrical parts in the control box for 5 minutes after the | L to N : AC 220/230/240V unit is stopped. 1 to 2/N : AC 220/230/240V 2/N to 3 : Normal if the voltage oscillates between DC 0 and approx. 20V Color symbol | ◆ Voltage check in PCB Mark | Color | The normal range is as follows. Display | Voltage range BK | Black | Power source ① DC280V DC230V - DC310V BL | Blue | ② DC220V DC218V - DC222V | 1 Phase ③ DC213V DC212V - DC214V | AC200-240V 50Hz BR | Brown ④ DC215V DC214V - DC216V OR | Orange | ⑤ DC225V DC224V - DC226V | Indoor ⑥ DC 2.5V DC 2.3V - DC 22.5V | unit RD | Red WH | White | TERMINAL | N L BLOCK YE | Yellow TB1 Y ／ G | Yellow ／ Green | 2

◆ Inspection of outdoor fan motor | ◆ Inspection of electronic ◆ Inspection power transistor See page 100. | expansion valve Remove the faston terminal and test See page 100. output voltage ◆ Inspection of resistance value of sensor Remove the connector and check the resistance value. See the section of sensor characteristics on page 90.
