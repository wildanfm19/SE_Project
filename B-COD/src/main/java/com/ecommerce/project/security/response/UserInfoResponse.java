package com.ecommerce.project.security.response;

import java.util.List;

public class UserInfoResponse {
    private Long id;
    private String username;
    private String email;
    private String nim;
    private String jurusan;
    private String phone;
    private boolean verifiedBinusian;
    private List<String> roles;
    private String jwtToken; // Tetap pertahankan untuk kompatibilitas

    // Constructor untuk login (dengan token)
    public UserInfoResponse(Long id, String username, String email, String nim,
                            String jurusan, String phone, boolean verifiedBinusian,
                            List<String> roles, String jwtToken) {
        this.id = id;
        this.username = username;
        this.email = email;
        this.nim = nim;
        this.jurusan = jurusan;
        this.phone = phone;
        this.verifiedBinusian = verifiedBinusian;
        this.roles = roles;
        this.jwtToken = jwtToken;
    }

    // Constructor untuk profile (tanpa token)
    public UserInfoResponse(Long id, String username, String email, String nim,
                            String jurusan, String phone, boolean verifiedBinusian,
                            List<String> roles) {
        this(id, username, email, nim, jurusan, phone, verifiedBinusian, roles, null);
    }

    // Tetap pertahankan constructor lama untuk kompatibilitas
    public UserInfoResponse(Long id, String username, List<String> roles, String jwtToken) {
        this(id, username, null, null, null, null, false, roles, jwtToken);
    }

    public UserInfoResponse(Long id, String username, List<String> roles) {
        this(id, username, null, null, null, null, false, roles, null);
    }

    // Getters & Setters
    public Long getId() {
        return id;
    }

    // ... (generate semua getter dan setter)
    public String getEmail() {
        return email;
    }

    public String getUsername() {
        return username;
    }

    public void setUsername(String username) {
        this.username = username;
    }

    public String getNim() {
        return nim;
    }

    public String getJurusan() {
        return jurusan;
    }

    public String getPhone() {
        return phone;
    }

    public boolean isVerifiedBinusian() {
        return verifiedBinusian;
    }

    public String getJwtToken() {
        return jwtToken;
    }

    public void setJwtToken(String jwtToken) {
        this.jwtToken = jwtToken;
    }
// ... (methods lainnya tetap sama)
}