package com.ecommerce.project.service;

import com.ecommerce.project.model.CodLocation;

import java.util.List;

public interface CodLocationService {

    // Mendapatkan semua lokasi COD aktif
    List<CodLocation> getActiveLocations();

    // Menambah lokasi COD baru
    CodLocation addLocation(CodLocation location);

    // Mengubah status lokasi (aktif/tidak aktif)
    CodLocation updateLocationStatus(Long locationId, boolean active);

}
