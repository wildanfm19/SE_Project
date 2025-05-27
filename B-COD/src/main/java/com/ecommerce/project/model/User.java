package com.ecommerce.project.model;

import jakarta.persistence.*;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;
import lombok.*;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

@Entity
@Data
@NoArgsConstructor
@Table(name = "users",
        uniqueConstraints = {
        @UniqueConstraint(columnNames = "username"),
        @UniqueConstraint(columnNames = "email")
        })
public class User {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "user_id")
    private Long userId;

    @NotBlank
    @Size(max = 20)
    @Column(name = "username")
    private String userName;

    @NotBlank
    @Size(max = 50)
    @Email
    @Column(name = "email")
    private String email;

    @NotBlank
    @Size(max = 120)
    @Column(name = "password")
    private String password;

    public User(String userName, String email, String password) {
        this.userName = userName;
        this.email = email;
        this.password = password;
    }

    @Setter
    @Getter
    @ManyToMany(cascade = {CascadeType.PERSIST, CascadeType.MERGE},
                fetch = FetchType.EAGER)
    @JoinTable(name = "user_role",
                joinColumns = @JoinColumn(name = "user_id"),
                inverseJoinColumns = @JoinColumn(name = "role_id"))
    private Set<Role> roles = new HashSet<>();

    @Getter
    @Setter
    @OneToMany(mappedBy = "user", cascade = {CascadeType.PERSIST, CascadeType.MERGE}, orphanRemoval = true)
//    @JoinTable(name = "user_address",
//                joinColumns = @JoinColumn(name = "user_id"),
//                inverseJoinColumns = @JoinColumn(name = "address_id"))
    private List<Address> addresses = new ArrayList<>();

    @ToString.Exclude
    @OneToOne(mappedBy = "user", cascade = { CascadeType.PERSIST, CascadeType.MERGE}, orphanRemoval = true)
    private Cart cart;

    @ToString.Exclude
    @OneToMany(mappedBy = "user",
            cascade = {CascadeType.PERSIST, CascadeType.MERGE},
            orphanRemoval = true)
    private Set<Product> products;


    // COD FIELD
    @Column(unique = true, length = 10)
    @Pattern(regexp = "^2\\d{9}$", message = "NIM harus diawali 2 dan 10 digit angka")
    private String nim;  // Format: 2xxxxxxxxx (10 digits total)

    // Updated phone field (starts with 08)
    @Column(length = 12)
    @Pattern(regexp = "^08\\d{8,10}$", message = "Nomor HP harus diawali 08 dan 10-12 digit")
    private String phone;  // Format: 08xxxxxxxx

    private String jurusan;

    @Column(name = "is_verified_binusian")
    private Boolean isVerifiedBinusian = false;  // Auto-verifikasi jika email Binus

    // Getter dan Setter
    public Boolean getIsVerifiedBinusian() {
        return isVerifiedBinusian;
    }

    public void setVerifiedBinusian(Boolean isVerifiedBinusian) {
        this.isVerifiedBinusian = isVerifiedBinusian;
    }

}
