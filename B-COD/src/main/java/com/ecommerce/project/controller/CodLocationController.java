package com.ecommerce.project.controller;

import com.ecommerce.project.model.CodLocation;
import com.ecommerce.project.service.CodLocationService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api")
public class CodLocationController {

    @Autowired
    private CodLocationService locationService;

    // Mendapatkan semua lokasi aktif
    @GetMapping("/cod-locations")
    public ResponseEntity<List<CodLocation>> getActiveLocations() {
        return ResponseEntity.ok(locationService.getActiveLocations());
    }

    // Menambah lokasi baru
    @PostMapping("/admin/cod-locations")
    public ResponseEntity<CodLocation> addLocation(@RequestBody CodLocation location) {
        CodLocation newLocation = locationService.addLocation(location);
        return ResponseEntity.ok(newLocation);
    }

    // Update status (aktif/nonaktif)
    @PutMapping("/{id}/status")
    public ResponseEntity<CodLocation> updateLocationStatus(@PathVariable Long id, @RequestParam boolean active) {
        CodLocation updatedLocation = locationService.updateLocationStatus(id, active);
        return ResponseEntity.ok(updatedLocation);
    }
}