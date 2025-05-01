package com.ecommerce.project.securityDemo;

import lombok.AllArgsConstructor;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.util.List;

@Data
@NoArgsConstructor
@AllArgsConstructor
public class LoginResponse {


    private String username;
    private List<String> roles;
    private String jwtToken;

}
