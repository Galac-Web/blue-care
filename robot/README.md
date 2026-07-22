# Robot pe alt PC → export pe server blu-car.ro

Robotul ruleaza **oriunde** (ex: `D:\Project\Robot_python`).  
Nu are nevoie de Laragon. Trimite datele la **serverul live**.

## Setup (o singura data)

1. Copiaza in folderul robot:
   - `robot1.py`
   - `robot_config.json` (sau redenumeste `robot_config.remote.json`)

2. Instaleaza dependentele Python:
```cmd
cd c:\laragon\www\blu-car.ro\robot
python -m pip install -r requirements.txt
```

3. Editeaza `robot_config.json`:
```json
{
    "mode": "remote",
    "site_api_url": "https://blu-car.ro/api/robot-oem.php",
    "site_api_urls": ["https://blu-car.ro/api/robot-oem.php"],
    "monitor_url": "https://blu-car.ro/robot-monitor.php",
    "robot_api_key": ""
}
```

4. Pornire:
```cmd
cd c:\laragon\www\blu-car.ro\robot
python robot1.py
```

La start trebuie sa vezi: `Conexiune server: OK`

## Comanda scanare

```cmd
curl -X POST http://127.0.0.1:5000/comanda -H "Content-Type: application/json" -d "{\"cont_id\":\"gbg_user_01\",\"user\":\"EMAIL\",\"pass\":\"PAROLA\"}"
```

## Ce se intampla

1. Robot scaneaza gbg-eshop (local, Selenium)
2. Gaseste `cod_articol` + `coduri_oem`
3. **POST** la `https://blu-car.ro/api/robot-oem.php`
4. Serverul apeleaza TecDoc, salveaza produsul in DB
5. Vezi progresul: https://blu-car.ro/robot-monitor.php

## Fara config file

```cmd
set BLU_SITE_API=https://blu-car.ro/api/robot-oem.php
python robot1.py
```

## Optional: cheie securitate

In `.env` pe server (`blu-car.ro`):
```
ROBOT_API_KEY=un_secret_lung
```

Acelasi in `robot_config.json` → `robot_api_key`.
