# Implémentation ANPR - Code Examples & SQL

## 1. AUTHORITY VEHICLES TABLE & LOGIC

### SQL Creation
```sql
-- Authority vehicles whitelist (liste blanche)
CREATE TABLE IF NOT EXISTS authority_vehicles (
    id VARCHAR(120) NOT NULL PRIMARY KEY,
    plate_number VARCHAR(40) NOT NULL UNIQUE,
    authority_type VARCHAR(80) NOT NULL,     -- 'police'|'fire'|'ambulance'|'government'|'customs'|'vip'
    department VARCHAR(120),                  -- ex: "PNC Kinshasa", "BCKG Montée"
    vehicle_type VARCHAR(80),                 -- ex: "patrol_car", "ambulance", "fire_truck"
    operator_name VARCHAR(190),
    operator_phone VARCHAR(80),
    priority_level INT DEFAULT 5,             -- 1=critical, 5=normal
    active TINYINT(1) NOT NULL DEFAULT 1,
    added_by VARCHAR(190),
    notes TEXT,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plate (plate_number),
    INDEX idx_authority (authority_type),
    INDEX idx_active (active)
);

-- Insert examples
INSERT INTO authority_vehicles VALUES
('auth-001', 'PNC2024001', 'police', 'PNC Kasavubu', 'patrol_car', 'Agent Smith', '0815123456', 1, 1, 'Admin', 'Main patrol', NOW(), NOW()),
('auth-002', 'BCKG2024010', 'fire', 'BCKG Montée', 'fire_truck', 'Chef Pompiers', '0815654321', 2, 1, 'Admin', 'Intervention rapide', NOW(), NOW()),
('auth-003', 'AMB2024005', 'ambulance', 'Clinique St Joseph', 'ambulance', 'Dispatcher', '0815999999', 2, 1, 'Admin', 'Urgences 24/7', NOW(), NOW());
```

### Python Detection Logic (drone-api/vision_service.py)

```python
def _watchlist_and_authority_map(self) -> tuple[dict[str, dict], dict[str, dict]]:
    """
    Charge les listes noire (watchlist) et blanche (authorities) depuis le store ou Redis.
    Returns: (watchlist_dict, authority_dict)
    """
    # Cache Redis (TTL 5 min)
    if _redis_client is not None:
        try:
            cached_watch = _redis_client.get('vigilix:watchlist:map')
            cached_auth = _redis_client.get('vigilix:authority:map')
            if cached_watch and cached_auth:
                return json.loads(cached_watch), json.loads(cached_auth)
        except Exception:
            pass

    store = self._load_store()
    
    # Watchlist (liste noire)
    watchlist_rows = store.get('vehicle_watchlist', [])
    watchlist: dict[str, dict] = {}
    for row in watchlist_rows:
        if not isinstance(row, dict) or not row.get('active', True):
            continue
        plate = self._normalize_plate(str(row.get('plate_number', '')))
        if plate:
            watchlist[plate] = row
    
    # Authority (liste blanche)
    authority_rows = store.get('authority_vehicles', [])
    authority: dict[str, dict] = {}
    for row in authority_rows:
        if not isinstance(row, dict) or not row.get('active', True):
            continue
        plate = self._normalize_plate(str(row.get('plate_number', '')))
        if plate:
            authority[plate] = row
    
    # Cache dans Redis
    if _redis_client is not None:
        try:
            _redis_client.setex('vigilix:watchlist:map', 300, json.dumps(watchlist, ensure_ascii=False))
            _redis_client.setex('vigilix:authority:map', 300, json.dumps(authority, ensure_ascii=False))
        except Exception:
            pass
    
    return watchlist, authority


def _detect_plates(self, frame: np.ndarray, vehicles: list[dict[str, Any]]) -> list[PlateHit]:
    """
    Détecte plaques avec classification: authorized/authority/unauthorized
    """
    cascade = self._plate_detector()
    watchlist, authority = self._watchlist_and_authority_map()  # NOUVEAU
    hits: list[PlateHit] = []
    seen: set[str] = set()

    candidate_regions = [vehicle['bbox'] for vehicle in vehicles]
    if not candidate_regions:
        height, width = frame.shape[:2]
        candidate_regions = [[0, 0, width - 1, height - 1]]

    for vehicle_box in candidate_regions:
        start_x, start_y, end_x, end_y = vehicle_box
        roi = frame[start_y:end_y, start_x:end_x]
        if roi.size == 0:
            continue

        gray = cv2.cvtColor(roi, cv2.COLOR_BGR2GRAY)
        gray = cv2.bilateralFilter(gray, 9, 75, 75)
        detections = cascade.detectMultiScale(gray, scaleFactor=1.08, minNeighbors=4, minSize=(48, 16))

        for plate_x, plate_y, plate_w, plate_h in detections:
            crop = roi[plate_y:plate_y + plate_h, plate_x:plate_x + plate_w]
            text, confidence = self._ocr_plate(crop)
            if len(text) < 5:
                continue

            global_box = [
                int(start_x + plate_x),
                int(start_y + plate_y),
                int(start_x + plate_x + plate_w),
                int(start_y + plate_y + plate_h),
            ]
            dedupe_key = f'{text}:{global_box}'
            if dedupe_key in seen:
                continue
            seen.add(dedupe_key)

            # NOUVEAU: Classification 3-état
            auth_status = 'authorized'
            watchlist_match = None
            authority_match = None
            
            if text in authority:
                auth_status = 'authority'  # NOUVEAU status
                authority_match = authority[text]
            elif text in watchlist:
                auth_status = 'unauthorized'
                watchlist_match = watchlist[text]
            
            hits.append(PlateHit(
                plate_number=text,
                confidence=confidence,
                bbox=global_box,
                auth_status=auth_status,
                watchlist_match=watchlist_match,
                authority_match=authority_match,  # NOUVEAU field
            ))

    return hits
```

### dataclass Update (PlateHit)

```python
@dataclass(frozen=True)
class PlateHit:
    plate_number: str
    confidence: float
    bbox: list[int]
    auth_status: str                          # 'authorized'|'authority'|'unauthorized'
    watchlist_match: dict[str, Any] | None
    authority_match: dict[str, Any] | None = None  # NOUVEAU
```

---

## 2. "SANS QR" EXEMPTION SYSTEM

### SQL Modifications

```sql
-- Ajouter champs à vehicle_watchlist
ALTER TABLE vehicle_watchlist ADD COLUMN (
    has_qr TINYINT(1) DEFAULT 1,                        -- 1=QR disponible, 0=pas de QR
    authentication_method VARCHAR(40) DEFAULT 'qr',    -- 'qr'|'manual'|'exempted'
    exemption_reason VARCHAR(255),                     -- "Plaque pré-BVMR", etc
    exemption_until DATE,                              -- Jusqu'à quelle date
    qr_verified_at DATETIME,                           -- Dernière vérification QR
    INDEX idx_has_qr (has_qr),
    INDEX idx_exemption_until (exemption_until)
);
```

### PHP Interface (admin/road-control.php extension)

```php
<?php
// Dans le formulaire add_watchlist

if ($action === 'add_watchlist') {
    // ... validation existante ...
    
    $plateNumber = vxl_road_normalize_plate((string) ($_POST['plate_number'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $hasQr = (int) ($_POST['has_qr'] ?? 1);  // NOUVEAU: 1 ou 0
    $authMethod = trim((string) ($_POST['authentication_method'] ?? 'qr')) ?: 'qr';
    $exemptionReason = trim((string) ($_POST['exemption_reason'] ?? ''));
    $exemptionUntil = trim((string) ($_POST['exemption_until'] ?? ''));
    
    if ($plateNumber === '' || strlen($plateNumber) < 5) {
        $feedbackError = 'Entrez une plaque exploitable.';
    } elseif ($reason === '') {
        $feedbackError = 'Indiquez pourquoi ce véhicule doit être surveillé.';
    } else {
        $watchlist = isset($store['vehicle_watchlist']) && is_array($store['vehicle_watchlist'])
            ? array_values($store['vehicle_watchlist'])
            : [];
        $updated = false;

        foreach ($watchlist as &$entry) {
            if (!is_array($entry) || vxl_road_normalize_plate((string) ($entry['plate_number'] ?? '')) !== $plateNumber) {
                continue;
            }

            // Mise à jour existante
            $entry['reason'] = $reason;
            $entry['has_qr'] = $hasQr;                      // NOUVEAU
            $entry['authentication_method'] = $authMethod;  // NOUVEAU
            if ($hasQr === 0) {
                $entry['exemption_reason'] = $exemptionReason;
                $entry['exemption_until'] = $exemptionUntil;
            }
            $entry['active'] = true;
            $entry['updated_at'] = date('c');
            $updated = true;
            break;
        }
        unset($entry);

        if (!$updated) {
            $newEntry = [
                'id' => VGX_PREFIX_WATCH . bin2hex(random_bytes(6)),
                'plate_number' => $plateNumber,
                'reason' => $reason,
                'has_qr' => $hasQr,                        // NOUVEAU
                'authentication_method' => $authMethod,    // NOUVEAU
                'exemption_reason' => $hasQr === 0 ? $exemptionReason : null,  // NOUVEAU
                'exemption_until' => $hasQr === 0 ? $exemptionUntil : null,    // NOUVEAU
                'active' => true,
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];
            $watchlist[] = $newEntry;
        }

        $store['vehicle_watchlist'] = $watchlist;
        if (function_exists('vgx_store_save')) {
            vgx_store_save($store);
        }
        
        $message = $updated ? "Mise à jour" : "Ajoutée";
        if ($hasQr === 0) {
            $message .= " (SANS QR, exemption jusqu'au $exemptionUntil)";
        }
        $feedbackSuccess = "Plaque $plateNumber $message à la watchlist.";
    }
}
?>
```

### HTML Form Snippet

```html
<form method="post" class="form-add-watchlist">
    <input type="hidden" name="action" value="add_watchlist">
    
    <div class="form-group">
        <label>Plaque:</label>
        <input type="text" name="plate_number" required pattern="[A-Z0-9]{5,}" placeholder="CD2024ABC">
    </div>
    
    <div class="form-group">
        <label>Raison surveillance:</label>
        <textarea name="reason" required></textarea>
    </div>
    
    <!-- NOUVEAU: QR Status -->
    <div class="form-group">
        <label>
            <input type="checkbox" name="has_qr" value="1" checked>
            Véhicule a un code QR/BVMR
        </label>
    </div>
    
    <!-- NOUVEAU: Exemption -->
    <fieldset id="exemption-fields" style="display:none;">
        <legend>Exemption (véhicule sans QR)</legend>
        
        <div class="form-group">
            <label>Raison exemption:</label>
            <select name="authentication_method">
                <option value="manual">Contrôle manuel</option>
                <option value="exempted">Exemption spéciale</option>
                <option value="pending">En attente vérification</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Détail exemption:</label>
            <textarea name="exemption_reason" placeholder="Plaque pré-BVMR, véhicule importé, etc."></textarea>
        </div>
        
        <div class="form-group">
            <label>Exemption valide jusqu'au:</label>
            <input type="date" name="exemption_until">
        </div>
    </fieldset>
    
    <button type="submit" class="btn btn-primary">Ajouter à watchlist</button>
</form>

<script>
// Toggle exemption fields based on QR checkbox
document.querySelector('input[name="has_qr"]').addEventListener('change', function(e) {
    document.getElementById('exemption-fields').style.display = e.target.checked ? 'none' : 'block';
});
</script>
```

---

## 3. TEMPORAL DEDUPLICATION

### Python Implementation (vision_service.py)

```python
def _is_duplicate_plate_read(self, plate_number: str, source_id: str, bbox: list[int], store: dict) -> bool:
    """
    Détecte si cette plaque a déjà été lue dans les 15 dernières secondes
    depuis la même source avec une bbox similaire.
    """
    recent_threshold = time.time() - 15  # 15 secondes
    plate_reads = store.get('plate_reads', [])
    
    if not isinstance(plate_reads, list):
        return False
    
    for read in plate_reads[-20:]:  # Check recent reads only
        if not isinstance(read, dict):
            continue
        
        # Même plaque + même source?
        if (self._normalize_plate(str(read.get('plate_number', ''))) != plate_number or
            str(read.get('source_id', '')) != source_id):
            continue
        
        # Timestamp récent?
        try:
            read_ts = datetime.fromisoformat(str(read.get('read_at', ''))).timestamp()
            if read_ts < recent_threshold:
                continue  # Trop vieux
        except (ValueError, AttributeError):
            continue
        
        # bbox proche (distance euclide < 50 pixels)?
        try:
            prev_bbox = read.get('bbox', [])
            if self._bbox_distance(bbox, prev_bbox) < 50:
                return True  # Doublon détecté
        except (ValueError, TypeError):
            pass
    
    return False


def _bbox_distance(self, bbox1: list[int], bbox2: list[int]) -> float:
    """Calcule distance euclidienne entre 2 bounding boxes (centres)."""
    if len(bbox1) < 4 or len(bbox2) < 4:
        return float('inf')
    
    center1 = ((bbox1[0] + bbox1[2]) / 2, (bbox1[1] + bbox1[3]) / 2)
    center2 = ((bbox2[0] + bbox2[2]) / 2, (bbox2[1] + bbox2[3]) / 2)
    
    dist = ((center1[0] - center2[0]) ** 2 + (center1[1] - center2[1]) ** 2) ** 0.5
    return dist


def _persist_plate_hits(
    self,
    hits: list[PlateHit],
    *,
    source_type: str,
    source_id: str,
    source_label: str,
) -> list[dict[str, Any]]:
    store = self._load_store()
    rows = store.get('plate_reads', [])
    if not isinstance(rows, list):
        rows = []

    now = datetime.now(timezone.utc).isoformat()
    persisted: list[dict[str, Any]] = []
    
    for hit in hits:
        # NOUVEAU: Check deduplication
        if self._is_duplicate_plate_read(hit.plate_number, source_id, hit.bbox, store):
            # Skip doublon
            continue
        
        # Persist normalement
        entry = {
            'id': f'plate-read-{uuid4().hex[:12]}',
            'plate_number': hit.plate_number,
            'auth_status': hit.auth_status,
            'source': source_label or source_type,
            'source_type': source_type,
            'source_id': source_id,
            'bbox': hit.bbox,
            'confidence': hit.confidence,
            'watchlist_match': hit.watchlist_match,
            'authority_match': getattr(hit, 'authority_match', None),  # NOUVEAU
            'read_at': now,
        }
        rows.append(entry)
        persisted.append(entry)
    
    store['plate_reads'] = rows[-500:]  # Keep last 500
    self._save_store(store)
    
    return persisted
```

---

## 4. BVMR INTEGRATION (Placeholder)

### Wrapper API (includes/bvmr.php)

```php
<?php

declare(strict_types=1);

// BVMR Integration - Bureau Véhicules Moteur Reconnus (National Database)
// TODO: Replace with real BVMR API endpoint

if (!function_exists('vgx_bvmr_check')) {
    /**
     * Vérifie une plaque contre la base nationale BVMR
     * 
     * @param string $plate_number Plaque normalisée (CD2024ABC)
     * @return array {
     *     'valid': bool,
     *     'exists': bool,
     *     'owner': string|null,
     *     'expiry': string|null (ISO date),
     *     'stolen': bool,
     *     'blacklisted': bool,
     *     'error': string|null,
     *     'source': 'bvmr'|'local'|'cache'
     * }
     */
    function vgx_bvmr_check(string $plate_number): array
    {
        $plate = strtoupper(trim(preg_replace('/[^A-Z0-9]/', '', $plate_number)));
        
        if ($plate === '' || strlen($plate) < 5) {
            return ['valid' => false, 'error' => 'Plaque invalide', 'source' => 'local'];
        }
        
        // Check Redis cache first (TTL 1 week)
        if (function_exists('vgx_redis') && $redis = vgx_redis()) {
            try {
                $cached = $redis->get("vigilix:bvmr:$plate");
                if ($cached) {
                    $result = json_decode($cached, true);
                    $result['source'] = 'cache';
                    return $result;
                }
            } catch (Exception $e) {
                // Cache miss, proceed to BVMR call
            }
        }
        
        // TODO: Call real BVMR API
        // For now, return stub response
        $result = [
            'valid' => true,
            'exists' => true,
            'owner' => null,           // Name car owner
            'expiry' => null,           // Registration expiry
            'stolen' => false,
            'blacklisted' => false,
            'error' => null,
            'source' => 'bvmr',
        ];
        
        // Cache result
        if (function_exists('vgx_redis') && $redis = vgx_redis()) {
            try {
                $redis->setex("vigilix:bvmr:$plate", 604800, json_encode($result)); // 1 week
            } catch (Exception $e) {
                // Cache failure, continue
            }
        }
        
        return $result;
    }
}

if (!function_exists('vgx_bvmr_bulk_check')) {
    /**
     * Vérifie plusieurs plaques en une seule requête (batch)
     */
    function vgx_bvmr_bulk_check(array $plate_numbers): array
    {
        $results = [];
        foreach ($plate_numbers as $plate) {
            $results[$plate] = vgx_bvmr_check($plate);
        }
        return $results;
    }
}
```

### Usage in road-control.php

```php
// Après analyse vision
if ($analysisResult && function_exists('vgx_bvmr_check')) {
    $plates = array_map(fn($p) => $p['plate_number'], $analysisResult['plates'] ?? []);
    
    foreach ($plates as $plate_num) {
        $bvmr = vgx_bvmr_check($plate_num);
        
        if ($bvmr['stolen']) {
            // ALERTE CRITIQUE: Véhicule volé!
            $feedbackError .= "\n🚨 VÉHICULE VOLÉ: $plate_num (BVMR)";
        }
        
        if ($bvmr['blacklisted']) {
            // Alerte court terme
            $feedbackError .= "\n⚠️ Plaque en liste noire nationale: $plate_num";
        }
    }
}
```

---

## 5. IMPROVE OCR CONFIDENCE SCORING

### Current vs Improved

```python
# CURRENT (problématique)
confidence = min(0.99, max(0.5, len(text) / 9))

# IMPROVED: Utiliser confidence EasyOCR directement
def _ocr_plate_easyocr_improved(self, plate_image: np.ndarray) -> tuple[str, float]:
    reader = self._easyocr_reader()
    if reader is None:
        return '', 0.0

    grayscale = cv2.cvtColor(plate_image, cv2.COLOR_BGR2GRAY)
    scaled = cv2.resize(grayscale, None, fx=2.5, fy=2.5, interpolation=cv2.INTER_CUBIC)
    normalized = cv2.bilateralFilter(scaled, 9, 75, 75)
    _, thresholded = cv2.threshold(normalized, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

    try:
        results = reader.readtext(thresholded, detail=1, paragraph=False, text_threshold=0.55, low_text=0.25)
    except Exception:
        return '', 0.0

    best_text = ''
    best_confidence = 0.0
    text_variants = []
    
    for bbox, candidate, confidence in results:
        text = self._normalize_plate(str(candidate))
        if len(text) < 5:
            continue
        
        confidence_value = float(confidence)
        text_variants.append((text, confidence_value))
        
        if confidence_value > best_confidence:
            best_text = text
            best_confidence = confidence_value

    if best_text == '':
        return '', 0.0

    # Confidence boosted if multiple OCR variants agree
    agreement_bonus = 0.0
    if len(text_variants) > 1:
        top_variants = sorted(text_variants, key=lambda x: x[1], reverse=True)[:3]
        if len(set(v[0] for v in top_variants)) == 1:  # All same text
            agreement_bonus = 0.08  # +8% if consensus
    
    final_confidence = min(0.99, best_confidence + agreement_bonus)
    return best_text, round(final_confidence, 4)
```

