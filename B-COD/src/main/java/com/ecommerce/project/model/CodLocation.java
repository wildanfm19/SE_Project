package com.ecommerce.project.model;

import jakarta.persistence.*;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import lombok.AllArgsConstructor;
import lombok.Data;
import lombok.NoArgsConstructor;

@Entity
@Data
@NoArgsConstructor
@AllArgsConstructor
public class CodLocation {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotBlank(message = "Location name cannot be blank")
    @Size(min = 3, max = 100, message = "Location name must be between 3 and 100 characters")
    private String name;       // contoh: "Kantin Barat", "Aula Lt. 8"

    @NotBlank(message = "Building name cannot be blank")
    private String building;   // Gedung tempat COD, contoh: "ANGGREK", "ALAMANDA", dll.

    private String floor;      // Lantai tempat COD, contoh: "Lantai 2"

    @NotBlank(message = "Description cannot be blank")
    @Size(max = 500, message = "Description cannot exceed 500 characters")
    private String description; // Penjelasan detail lokasi

    private boolean active;    // Apakah lokasi COD ini tersedia? Default: true
}