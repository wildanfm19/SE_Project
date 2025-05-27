package com.ecommerce.project.repositories;

import com.ecommerce.project.model.CodLocation;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface CodLocationRepository extends JpaRepository<CodLocation,Long> {
    List<CodLocation> findByActiveTrue();
}
