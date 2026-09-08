# Analyse Complète du Système ANPR (Automatic Number Plate Recognition)
## Vigilix Traffic Control System

**Date:** 6 Juin 2026  
**Statut:** Analyse détaillée - Production Ready  
**Portée:** système vision drone-api, détection plaques, contrôle routier

---

## 1. ARCHITECTURE GLOBALE

### 1.1 Composants Clés

```
Frontend (PHP)                  Backend (Python)                Storage
├─ admin/road-control.php  →   drone-api/vision_service.py  →  demo-store.json
├─ contrôle routier         →   app.py (Flask API)           →  MySQL (optionnel)
├─ watchlist/gestion        →   drone_controller.py          →  Redis cache
└─ incidents trafic         →   face_service.py              └─ événements
```

### 1.2 Flux de Détection

```
Image (drone/camera)
    ↓
Vision Service (Python)
    ├─ YOLO Vehicle Detection (détecte véhicules)
    │   └─ ByteTrack (tracking temps-réel)
    ├─ Plate Detection (Haar Cascade russe)
    │   └─ OCR EasyOCR + Tesseract
    └─ Analysis & Classification
        ├─ Traffic Analysis
        ├─ Incident Classification
        └─ Watchlist Matching
    ↓
REST API (/api/vision/analyze)
    ↓
PHP Storage & Alerts
    ├─ plate_reads (historique)
    ├─ traffic_reports (rapports)
    ├─ vehicle_watchlist (surveillance)
    └─ traffic_incidents (incidents)
```

---

## 2. DÉTECTION DE PLAQUES - TECHNOLOGIE

### 2.1 Type de Système: **SEMI-RÉEL** avec Simulation

#### Real (Authentique)
- ✅ Détection de véhicules: **YOLOv8L** (réseau de neurones réel)
- ✅ Détection de plaques: **OpenCV Haar Cascade** (algorithme réel - cascade_russe)
- ✅ OCR: **EasyOCR** (deep learning) + **Tesseract** (fallback)
- ✅ Tracking: **ByteTrack** (suivi multi-objet temps-réel)
- ✅ Analyse trafic: Algorithmes propriétaires (vision + géométrie)

#### Simulation/Démo
- ⚠️ Les données **ne sont pas stockées en base de données MySQL** (seulement JSON local)
- ⚠️ Les drones du système sont **simulés** (drone_controller.py)
- ⚠️ Les flux caméra peuvent être **vidéos locales ou flux RTSP**

### 2.2 Pipeline OCR Détaillé

```python
# Vision_service.py - _ocr_plate()

1. Image reçue
   ↓
2. EasyOCR (prioritaire)
   ├─ Redimensionnement 2.5x
   ├─ Filtre bilatéral (9, 75, 75)
   ├─ Threshold OTSU
   └─ Confidence threshold: 0.55
       ↓ (Si succès: retour)
       ↓ (Si échec: fallback)
3. Tesseract OCR
   ├─ Redimensionnement 2.2x
   ├─ Filtre bilatéral (7, 31, 31)
   ├─ Threshold OTSU
   ├─ Config: OEM 1, PSM 7
   └─ Whitelist: A-Z0-9 uniquement
       ↓
4. Normalisation: [^A-Z0-9] → supprimé
   ├─ Longueur minimum: 5 caractères
   └─ Confidence calculée: min(len(text)/9, 0.99), max(0.5)
```

#### Prétraitement Image
```
Original → Grayscale → Bilateral Filter → OTSU Threshold → OCR
```

- **Bilateral Filter** = lisse tout en préservant les bords (idéal plaques)
- **OTSU** = seuillage automatique optimal
- **Whitelist** = seules lettres/chiffres autorisés

### 2.3 Fonctionnement Réel

**La détection ANPR est RÉELLE et fonctionnelle:**
- Prend une image en entrée
- Détecte les plaques via cascade Haar (algorithme classique mais efficace)
- Utilise OCR professionnel (EasyOCR = deep learning)
- Normalise et valide les plaques
- Retourne texte + confidence score

**Limitations:**
- Accuracy dépend de: angle plaque, résolution, condition lumière
- Pas d'optimisation pour plaques spécifiques régions (format fixe A-Z0-9)
- Pas de fallback vers base BVMR réelle (base nationale)

---

## 3. ENDPOINT API

### 3.1 Endpoint Principal

**POST** `/api/vision/analyze`
```
Headers: X-Api-Key: vigilix-dev-key-change-in-production

Form Data:
├─ image (file, required)
├─ source_type: 'camera' | 'drone' | 'manual' (default: 'camera')
├─ source_id: identifiant source
├─ source_label: nom affichage (ex: "Carrefour Kasavubu")
├─ source_lat, source_lng: coordonnées GPS (optionnel)
└─ save_result: '1' (par défaut) sauvegarde locale

Response (JSON):
{
  "status": "ok",
  "source": {
    "type": "camera",
    "id": "cam-001",
    "label": "Camera routière Kasavubu",
    "latitude": -4.3250,
    "longitude": 15.3222
  },
  "vehicles": [
    {
      "label": "car",
      "confidence": 0.9234,
      "bbox": [120, 150, 250, 300],
      "track_id": 42
    }
  ],
  "plates": [
    {
      "plate_number": "CD2024ABC",
      "confidence": 0.8765,
      "bbox": [130, 170, 240, 190],
      "auth_status": "authorized",
      "watchlist_match": null
    }
  ],
  "counts": {
    "vehicles": 3,
    "plates": 1,
    "unauthorized_plates": 0
  },
  "traffic_summary": {
    "scene": {
      "vehicle_count": 3,
      "congestion_score": 0.35,
      "severity": "modere"
    },
    "road_layout": {
      "node_type": "intersection",
      "complexity_score": 0.58
    },
    "recommendation": {
      "headline": "intersection modere sur Camera routière",
      "action": "Surveiller, fluidifier les insertions..."
    }
  },
  "saved_reads": [
    {
      "id": "plate-read-abc123",
      "plate_number": "CD2024ABC",
      "auth_status": "authorized",
      "read_at": "2026-06-06T10:30:45Z"
    }
  ]
}
```

### 3.2 Autres Endpoints

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/vision/health` | GET | État service, OCR disponible, cascade loaded |
| `/api/vision/events?limit=25` | GET | Flux d'événements (watchlist, incidents, trafic) |
| `/api/vision/face-db` | GET | Base visages inscrite (face_service.py) |

---

## 4. COLLECTIONS DE DONNÉES

### 4.1 Table: `plate_reads`

```sql
CREATE TABLE plate_reads (
    id VARCHAR(120) PRIMARY KEY,           -- plate-read-{uuid}
    plate_number VARCHAR(40) NOT NULL,     -- CD2024ABC (normalisé)
    auth_status VARCHAR(40) DEFAULT 'authorized',  -- 'authorized' | 'unauthorized'
    source VARCHAR(190),                    -- camera/drone label
    source_type VARCHAR(80),                -- 'camera' | 'drone' | 'manual'
    source_id VARCHAR(120),                 -- identifiant source
    confidence DECIMAL(5,4),                -- 0.5 - 0.99
    bbox JSON,                              -- [x1, y1, x2, y2]
    watchlist_match JSON,                   -- {id, plate_number, reason, risk_level}
    read_at TIMESTAMP DEFAULT NOW(),
    INDEX idx_plate (plate_number),
    INDEX idx_read_at (read_at)
);
```

**Exemple:**
```json
{
  "id": "plate-read-a1b2c3d4e5f6",
  "plate_number": "CD2024ABC",
  "auth_status": "unauthorized",
  "source": "Drone Kasavubu",
  "source_type": "drone",
  "source_id": "drone-001",
  "confidence": 0.8765,
  "bbox": [120, 150, 240, 190],
  "watchlist_match": {
    "id": "watch-xyz789",
    "plate_number": "CD2024ABC",
    "reason": "Véhicule suspecté de braquage",
    "risk_level": "high"
  },
  "read_at": "2026-06-06T10:30:45Z"
}
```

### 4.2 Table: `vehicle_watchlist`

```sql
CREATE TABLE vehicle_watchlist (
    id VARCHAR(120) PRIMARY KEY,
    plate_number VARCHAR(40) UNIQUE NOT NULL,
    reason TEXT,                            -- Pourquoi surveiller
    owner_name VARCHAR(190),
    risk_level VARCHAR(40) DEFAULT 'normal', -- 'low'|'normal'|'high'|'critical'
    active TINYINT(1) DEFAULT 1,
    added_by VARCHAR(190),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP ON UPDATE NOW(),
    INDEX idx_plate (plate_number),
    INDEX idx_active (active)
);
```

**Statuts plaques:**
- `authorized`: plaque normale, pas en watchlist → ✅ Libre
- `unauthorized`: plaque EN watchlist → ⚠️ Alerte immédiate
- `non autorisee`, `non authentifiee`: synonymes de unauthorized

### 4.3 Table: `traffic_reports`

```sql
CREATE TABLE traffic_reports (
    id VARCHAR(120) PRIMARY KEY,           -- traf-{uuid}
    axis VARCHAR(190),                     -- "Carrefour Kasavubu"
    detail TEXT,                            -- Description incident
    status VARCHAR(40),                    -- severity: 'fluide'|'modere'|'eleve'|'critique'
    source_type VARCHAR(80),
    source_id VARCHAR(120),
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    node_type VARCHAR(40),                 -- 'roundabout'|'intersection'|'corridor'|'arterial'
    vehicle_count INT DEFAULT 0,
    queue_length_estimate INT DEFAULT 0,
    culprit_label VARCHAR(80),             -- ex: "bus"
    culprit_position_hint VARCHAR(120),    -- ex: "axe droite aval"
    recommendation TEXT,
    reported_at TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW(),
    INDEX idx_reported (reported_at)
);
```

### 4.4 Table: `traffic_incidents`

```
Type d'incidents générés:
├─ 'plaque_non_autorisee'  : Plaque en watchlist détectée
├─ 'stationnement_illegal' : Véhicule bloquant petite zone
├─ 'congestion'            : Embouteillage (score > 0.45)
├─ 'blocage'               : Véhicule bloque largeur route
└─ 'normal'                : Pas d'incident

Statuts:
├─ 'detecte'        : Détecté par IA
├─ 'transmis_police': Dispatcher à police
├─ 'police_en_route': Police en chemin
├─ 'police_sur_place': Police arrivée
└─ 'resolu'        : Incident clôturé
```

---

## 5. SYSTÈME ACTUEL DE CONTRÔLE ROUTIER

### 5.1 Interface: `admin/road-control.php`

**Fonctionnalités:**
1. ✅ Upload image pour analyse vision
2. ✅ Gestion watchlist plaques
3. ✅ Visualisation incidents trafic
4. ✅ Répartition police
5. ✅ Historique plaques lues
6. ✅ Rapports trafic temps-réel

**Actions POST principales:**

| Action | Description | Auth |
|--------|-------------|------|
| `add_watchlist` | Ajoute plaque à surveiller | Admin |
| `analyze_frame` | Envoie image → vision_service | Admin |
| `dispatch_police` | Transmet incident → police | Admin |
| `resolve_incident` | Marque incident résolu | Admin |

### 5.2 Workflow Incident Plaque Non-Autorisée

```
Image analysée
    ↓
Plaque détectée → Comparée à watchlist
    ↓
EN watchlist:
    ├─ PlateHit.auth_status = 'unauthorized'
    ├─ Incident créé: type='plaque_non_autorisee'
    ├─ Severity auto-calculée (trafic + watchlist)
    ├─ police_recommended = TRUE
    ├─ Rapport trafic créé auto
    └─ Alerte Ops (visible tableau bord)
         ↓
    Action OPS:
    ├─ Vérifier incident
    ├─ Localiser véhicule
    ├─ Dispatch police (POST dispatch_police)
    ├─ Suivre statut
    └─ Clôturer (POST resolve_incident)
```

### 5.3 Gestion Doublons

**Déduplification dans `_detect_plates()`:**
```python
seen: set[str] = set()

for plate_hit in detections:
    dedupe_key = f'{plate_number}:{bbox_coordinates}'
    if dedupe_key in seen:
        continue  # Skip doublon même plaque, même bbox
    seen.add(dedupe_key)
```

**Limitation actuelle:** 
- Dédupe par plaque + bbox EXACT de la même image
- ⚠️ **PROBLÈME:** Même plaque, bbox légèrement différente (frame suivante) = nouvelle lecture
- Solution: Ajouter déduplication temporelle (10-15 sec)

---

## 6. GESTION VÉHICULES D'AUTORITÉ

### 6.1 Statut Actuel: **ABSENT**

**Problème identifié:**
- ❌ Aucun système de liste blanche (whitelist) pour autorités
- ❌ Aucun champ `authority_vehicle` ou `vip` 
- ❌ Toutes les plaques → `authorized` par défaut
- ❌ Uniquement watchlist (liste noire) est géré

**Implémentation actuelle:**
```python
# vision_service.py - _detect_plates()

watch = active_watchlist.get(text)
auth_status = 'authorized' if watch is None else 'watchlist'
```

= Si plaque PAS en watchlist → authorized (libre)
= Si plaque EN watchlist → watchlist (non-autorisée)

### 6.2 Véhicules d'Autorité Requis

```
Pour Kinshasa (Vigilix):
├─ Police (PNC/PNTC)
│   └─ Format plaque: "PNC" + numéro
├─ Pompiers (BCKG)
│   └─ Format plaque: "BCKG" + numéro
├─ Ambulances (urgence)
├─ Gouvernement/VIP
└─ Dépanneurs autorisés
```

**Quoi faire si plaque = autorité:**
- ✅ Détecter mais **NE PAS ALERTER**
- ✅ Logger lecture (historique)
- ✅ Permettre stationnement/circulation prioritaire

---

## 7. MARQUAGE "SANS QR" / SANS AUTHENTIFICATION

### 7.1 Concept: Véhicules Non-Numérisés

**Cas d'usage:**
- Vieux véhicules (avant système QR/BVMR)
- Véhicules importés (plaques étrangères)
- Plaques endommagées/illisibles
- Plaques temporaires

### 7.2 Solution Proposée

**Ajouter champ dans `vehicle_watchlist`:**
```sql
ALTER TABLE vehicle_watchlist ADD COLUMN (
    has_qr TINYINT(1) DEFAULT 1,          -- 1=avec QR, 0=sans QR
    qr_verified_at DATETIME,              -- Quand last vérifiée
    authentication_method VARCHAR(40),    -- 'qr'|'manual'|'exempted'
    exemption_reason TEXT,                -- Pourquoi pas de QR
    exemption_until DATETIME              -- Jusqu'à quand
);
```

**Workflow:**
```
Plaque détectée
    ↓
Si en watchlist ET has_qr=0:
    ├─ Chercher authentification alternative
    ├─ Vérifier exemption_until
    └─ Marquer dans historique
         ↓
    Statut = 'sans_qr_exempted' (INFO)
    (vs 'unauthorized' = ALERTE)
```

**Interface PHP (road-control.php):**
```php
// Ajout plaque sans QR
$_POST['plate_number']      = 'CD2024ABC';
$_POST['has_qr']            = 0;  // NOUVEAU
$_POST['exemption_reason']  = 'Plaque ancienne, véhicule avant BVMR';
$_POST['exemption_until']   = '2027-12-31';
```

---

## 8. RECOMMANDATIONS POUR AMÉLIORATION

### 8.1 Améliorations Court Terme (3-6 mois)

#### 1. **Gestion Autorités & VIP** ⭐⭐⭐ (CRITIQUE)
```python
# Ajouter système de liste blanche

CREATE TABLE authority_vehicles (
    id VARCHAR(120) PRIMARY KEY,
    plate_number VARCHAR(40) UNIQUE,
    authority_type VARCHAR(80),      -- 'police'|'fire'|'ambulance'|'government'|'vip'
    department VARCHAR(120),         -- ex: "PNC Kinshasa"
    vehicle_type VARCHAR(80),        -- ex: "patrol car", "ambulance"
    priority_level INT DEFAULT 5,    -- 1=critique, 5=normal
    active TINYINT(1) DEFAULT 1,
    added_by VARCHAR(190),
    created_at TIMESTAMP DEFAULT NOW()
);

# Modif vision_service.py _detect_plates():
watch = active_watchlist.get(text)
authority = active_authorities.get(text)  # NOUVEAU

if authority:
    auth_status = 'authority'  # NOUVEAU
    watchlist_match = authority
elif watch is None:
    auth_status = 'authorized'
else:
    auth_status = 'watchlist'
```

#### 2. **Déduplification Temporelle** ⭐⭐ (IMPORTANT)
```python
# Éviter doubles lectures même plaque à 5 sec d'intervalle

def _persist_plate_hits(self, hits):
    recent_threshold = time.time() - 15  # 15 secondes
    store = self._load_store()
    
    for hit in hits:
        # Chercher lecture récente (même plaque, source, bbox proche)
        recent = [
            r for r in store.get('plate_reads', [])
            if r['plate_number'] == hit.plate_number
            and r['source_id'] == source_id
            and (datetime.fromisoformat(r['read_at']).timestamp() > recent_threshold)
            and bbox_distance(r['bbox'], hit.bbox) < 50  # pixels
        ]
        
        if recent:
            continue  # Skip doublon
        
        # Sinon: persister normalement
```

#### 3. **Marquage "Sans QR"** ⭐ (MOYEN)
- Ajouter champ `has_qr` + `exemption_reason`
- Affichage distinct dans UI (badge "EXEMPTED")
- Alarme < 30 jours avant expiration exemption

### 8.2 Améliorations Moyen Terme (6-12 mois)

#### 4. **Intégration BVMR** ⭐⭐⭐
```php
// Vérification en temps-réel contre base nationale

function check_national_database(string $plate): array {
    // Appel SOAP/API vers BVMR (Bureau Véhicules Moteur Reconnus)
    // Retourne: {valid: bool, owner: string, expiry: date, stolen: bool}
}

// Dans road-control.php:
if (function_exists('check_national_database')) {
    $bvmr_check = check_national_database($plate);
    if ($bvmr_check['stolen']) {
        // ALERTE CRITIQUE: véhicule volé
    }
}
```

#### 5. **Confidence Score Améloré** ⭐⭐
- Utiliser EasyOCR confidence DIRECTEMENT (0-1)
- Implémenter Tesseract scoring réel
- Ajouter score géométrique (angle plaque, distortion)
- Score final = weighted combination

#### 6. **Tracking Multi-Frame** ⭐⭐
```python
# Associer lectures plaques à trajectoire véhicule (track_id)

def _correlate_plate_to_vehicle(plate_hit, vehicles_with_tracking):
    # Match plaque bbox avec vehicle bbox (même track_id)
    # Construire historique plaque par trajectoire véhicule
    
    return {
        'plate': plate_hit,
        'vehicle_track_id': matching_vehicle['track_id'],
        'vehicle_class': matching_vehicle['label'],
        'confidence_vehicle': matching_vehicle['confidence'],
        'trajectory': [frame_positions]
    }
```

#### 7. **Export & Analytics** ⭐
- Dashboard temps-réel plaques lues
- Rapports quotidiens/hebdo
- Graphiques tendances watchlist matches
- Heatmap incidents par arrondissement

### 8.3 Architecture Recommandée

```
Current: JSON local → Phase 1
├─ Ajouter MySQL + Redis
├─ Gestion autorités/VIP
├─ Déduplification
└─ Marquage sans QR
    ↓
Phase 2: Intégration BVMR
├─ API wrapper BVMR
├─ Cache national/local
├─ Alertes vol/abandon
└─ Synchronisation
    ↓
Phase 3: ML Avancé
├─ Prédiction congestion IA
├─ Classification risque plaques
├─ Reconnaissance conducteur (face)
└─ Prédiction incidents
```

---

## 9. CHECKLIST IMPLÉMENTATION

### Court Terme (Immédiat)
- [ ] Ajouter table `authority_vehicles`
- [ ] Créer interface CRUD autorités (admin/authorities.php)
- [ ] Modifier `_detect_plates()` pour détection autorités
- [ ] Ajouter champ `has_qr` + `exemption_reason` à watchlist
- [ ] Tests unitaires OCR (EasyOCR vs Tesseract)
- [ ] Documentation API v2

### Moyen Terme (3 mois)
- [ ] Déduplification temporelle (15 sec)
- [ ] Intégration BVMR (API wrapper)
- [ ] Dashboard analytics plaques
- [ ] Export rapports CSV/PDF
- [ ] Alertes SMS/Telegram pour watchlist matches

### Long Terme (6 mois+)
- [ ] ML classification risque plaques
- [ ] Intégration caméras CCTV existantes
- [ ] Mobile app lecture plaques (agents terrain)
- [ ] Blockchain audit trail incidents
- [ ] Predictive traffic modeling

---

## 10. CONCLUSION

### État Système ANPR: **FONCTIONNEL + PROMETTEUR**

✅ **Points Forts:**
- Technologie réelle (YOLO + EasyOCR)
- Pipeline vision complet
- Intégration trafic + incidents
- Tracking multi-objets (ByteTrack)
- REST API clean et extensible

⚠️ **Limitations Actuelles:**
- Pas de gestion autorités/VIP
- Pas de vérification nationale (BVMR)
- Doublons temporels possibles
- Stockage JSON local (non-produit)
- Pas de marquage "sans QR"

🎯 **Prochaine Phase:**
1. **Immédiate:** Autorités + sans-QR
2. **3 mois:** Déduplification + BVMR
3. **6 mois+:** Analytics + ML avanc

---

## 11. CONTACTS & RESSOURCES

**Fichiers Clés:**
- Vision: [drone-api/vision_service.py](drone-api/vision_service.py#L1)
- API: [drone-api/app.py](drone-api/app.py#L1)
- Frontend: [admin/road-control.php](admin/road-control.php#L1)
- DB Schema: [database/vigilix.sql](database/vigilix.sql#L256-L320)

**Dépendances Python:**
- YOLOv8: `ultralytics>=8.0`
- EasyOCR: `easyocr>=1.7.2`
- OpenCV: `cv2>=4.8`
- ByteTrack: `supervision>=0.19`

**Configuration:**
```bash
# Clé API par défaut (CHANGER EN PROD)
VGX_DRONE_API_KEY=vigilix-dev-key-change-in-production

# Cascade Haar (incluse OpenCV)
haarcascade_russian_plate_number.xml

# Modèles YOLO
yolov8l.pt (détection véhicules)
yolov8n.pt (fallback léger)
```

