package com.ecommerce.project.controller;

import com.ecommerce.project.model.AppRole;
import com.ecommerce.project.model.Role;
import com.ecommerce.project.model.User;
import com.ecommerce.project.repositories.RoleRepository;
import com.ecommerce.project.repositories.UserRepository;
import com.ecommerce.project.security.jwt.JwtUtils;
import com.ecommerce.project.security.request.LoginRequest;
import com.ecommerce.project.security.request.SignupRequest;
import com.ecommerce.project.security.response.MessageResponse;
import com.ecommerce.project.security.response.UserInfoResponse;
import com.ecommerce.project.security.services.UserDetailsImpl;
import jakarta.validation.Valid;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseCookie;
import org.springframework.http.ResponseEntity;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.AuthenticationException;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.*;

import java.util.*;
import java.util.stream.Collectors;

@RestController
@RequestMapping("/api/auth")
public class AuthController {

    @Autowired
    private JwtUtils jwtUtils;

    @Autowired
    private AuthenticationManager authenticationManager;

    @Autowired
    UserRepository userRepository;

    @Autowired
    RoleRepository roleRepository;

    @Autowired
    PasswordEncoder encoder;

    @PostMapping("/signin")
    public ResponseEntity<?> authenticateUser(@RequestBody LoginRequest loginRequest) {
        Authentication authentication;
        try {
            authentication = authenticationManager
                    .authenticate(new UsernamePasswordAuthenticationToken(loginRequest.getUserName(), loginRequest.getPassword()));
        } catch (AuthenticationException exception) {
            Map<String, Object> map = new HashMap<>();
            map.put("message", "Bad credentials");
            map.put("status", false);
            return new ResponseEntity<Object>(map, HttpStatus.NOT_FOUND);
        }

        SecurityContextHolder.getContext().setAuthentication(authentication);

        UserDetailsImpl userDetails = (UserDetailsImpl) authentication.getPrincipal();

        // Fetch complete user data from database
        User user = userRepository.findById(userDetails.getId())
                .orElseThrow(() -> new RuntimeException("User not found"));



        ResponseCookie jwtCookie = jwtUtils.generateJwtCookie(userDetails);
        System.out.println("GENERATED COOKIE : " + jwtCookie.toString());
        System.out.println("GENERATED COOKIE VALUE : " + jwtCookie.getValue());



        List<String> roles = userDetails.getAuthorities().stream()
                .map(item -> item.getAuthority())
                .collect(Collectors.toList());

        // Updated response with all user fields
        UserInfoResponse response = new UserInfoResponse(
                userDetails.getId(),
                user.getUserName(),
                user.getEmail(),
                user.getNim(),
                user.getJurusan(),
                user.getPhone(),
                user.getIsVerifiedBinusian(),
                roles,
                jwtCookie.getValue()
        );

        return ResponseEntity.ok()
                .header(HttpHeaders.SET_COOKIE, jwtCookie.toString())
                .body(response);
    }

    @PostMapping("/signup")
    public ResponseEntity<?> registerUser(@Valid @RequestBody SignupRequest signUpRequest) {
        // Validations
        if (userRepository.existsByUserName(signUpRequest.getUsername())) {
            return ResponseEntity.badRequest().body("Error: Username taken!");
        }
        if (userRepository.existsByEmail(signUpRequest.getEmail())) {
            return ResponseEntity.badRequest().body("Error: Email taken!");
        }
        if (userRepository.existsByNim(signUpRequest.getNim())) {
            return ResponseEntity.badRequest().body("Error: NIM already registered!");
        }

        // Create user
        User user = new User();
        user.setUserName(signUpRequest.getUsername());
        user.setEmail(signUpRequest.getEmail());
        user.setPassword(encoder.encode(signUpRequest.getPassword()));
        user.setNim(signUpRequest.getNim());
        user.setPhone(signUpRequest.getPhone());
        user.setJurusan(signUpRequest.getJurusan());
        user.setVerifiedBinusian(true); // Auto-verify

        // Set default role (ROLE_USER only)
        Role userRole = roleRepository.findByRoleName(AppRole.ROLE_USER)
                .orElseThrow(() -> new RuntimeException("Error: Role USER not found"));
        user.setRoles(Set.of(userRole));

        userRepository.save(user);
        return ResponseEntity.ok("Registration successful!");
    }

    @GetMapping("/username")
    public String currentUserName(Authentication authentication){
        if (authentication != null)
            return authentication.getName();
        else
            return "";
    }


    @GetMapping("/user")
    public ResponseEntity<?> getUserDetails(Authentication authentication) {
        // Get the complete user data from database
        User user = userRepository.findByUserName(authentication.getName())
                .orElseThrow(() -> new RuntimeException("User not found"));

        // Extract roles
        List<String> roles = user.getRoles().stream()
                .map(role -> role.getRoleName().name())
                .collect(Collectors.toList());

        // Build comprehensive response
        UserInfoResponse response = new UserInfoResponse(
                user.getUserId(),
                user.getUserName(),
                user.getEmail(),
                user.getNim(),
                user.getJurusan(),
                user.getPhone(),
                user.getIsVerifiedBinusian(),
                roles
        );

        return ResponseEntity.ok().body(response);
    }

    @PostMapping("/signout")
    public ResponseEntity<?> signoutUser(){
        ResponseCookie cookie = jwtUtils.getCleanJwtCookie();
        return ResponseEntity.ok().header(HttpHeaders.SET_COOKIE,
                        cookie.toString())
                .body(new MessageResponse("You've been signed out!"));
    }

    @PutMapping("/user/update-limited")
    public ResponseEntity<?> updateLimitedProfile(
            @RequestBody Map<String, String> updateData,
            Authentication authentication
    ) {
        User user = userRepository.findByUserName(authentication.getName())
                .orElseThrow(() -> new RuntimeException("User not found"));

        // Only allow updating username and phone
        if (updateData.containsKey("username")) {
            String newUsername = updateData.get("username");
            if (!user.getUserName().equals(newUsername)) {
                if (userRepository.existsByUserName(newUsername)) {
                    return ResponseEntity.badRequest().body("Username already taken");
                }
                user.setUserName(newUsername);
            }
        }

        if (updateData.containsKey("phone")) {
            user.setPhone(updateData.get("phone"));
        }

        userRepository.save(user);

        // Return updated user data
        List<String> roles = user.getRoles().stream()
                .map(role -> role.getRoleName().name())
                .collect(Collectors.toList());

        UserInfoResponse response = new UserInfoResponse(
                user.getUserId(),
                user.getUserName(),
                user.getEmail(),
                user.getNim(),
                user.getJurusan(),
                user.getPhone(),
                user.getIsVerifiedBinusian(),
                roles
        );

        return ResponseEntity.ok(response);
    }
}
