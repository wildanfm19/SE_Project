package com.ecommerce.project.security.request;

import com.ecommerce.project.validation.BinusEmail;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;
import lombok.Data;

import java.util.Set;

@Data
public class SignupRequest {
    @NotBlank
    @Size(min = 3, max = 20)
    private String username;

    @NotBlank
    @Size(max = 50)
    @BinusEmail
    private String email;

    private Set<String> role;

    @NotBlank
    @Size(min = 6, max = 40)
    private String password;


    // Updated NIM field (starts with 2, 10 digits total)
    @NotBlank
    @Pattern(regexp = "^2\\d{9}$", message = "NIM must start with 2 and be 10 digits total")
    private String nim;  // Format: 2xxxxxxxxx (10 digits)

    // Updated phone field (starts with 08)
    @NotBlank
    @Pattern(regexp = "^08\\d{8,10}$", message = "Phone must start with 08 and be 10-12 digits")
    private String phone;  // Format: 08xxxxxxxx

    @NotBlank
    private String jurusan;

    // Add getters and setters for new fields
    public String getNim() {
        return nim;
    }

    public void setNim(String nim) {
        this.nim = nim;
    }

    public String getPhone() {
        return phone;
    }

    public void setPhone(String phone) {
        this.phone = phone;
    }

    public Set<String> getRole() {
        return this.role;
    }

    public void setRole(Set<String> role) {
        this.role = role;
    }
}


