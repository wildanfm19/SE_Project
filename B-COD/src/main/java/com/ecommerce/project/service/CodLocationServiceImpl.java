package com.ecommerce.project.service;

import com.ecommerce.project.model.CodLocation;
import com.ecommerce.project.repositories.CodLocationRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class CodLocationServiceImpl implements CodLocationService {

    @Autowired
    private CodLocationRepository locationRepository;

    @Override
    public List<CodLocation> getActiveLocations() {
        // Hanya menampilkan lokasi yang aktif
        return locationRepository.findByActiveTrue();
    }

    @Override
    public CodLocation addLocation(CodLocation location) {
        return locationRepository.save(location);
    }

    @Override
    public CodLocation updateLocationStatus(Long locationId, boolean active) {
        CodLocation location = locationRepository.findById(locationId).orElseThrow(
                () -> new RuntimeException("Location not found with ID: " + locationId)
        );
        location.setActive(active);
        return locationRepository.save(location);
    }
}