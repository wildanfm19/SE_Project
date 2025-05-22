package com.ecommerce.project.validation;

import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;

public class BinusEmailValidator implements  ConstraintValidator<BinusEmail, String> {
    @Override
    public boolean isValid(String email, ConstraintValidatorContext context) {
        return email != null && email.toLowerCase().endsWith("@binus.ac.id");
    }
}
