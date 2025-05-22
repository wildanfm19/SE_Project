package com.ecommerce.project.validation;
import jakarta.validation.Constraint;
import jakarta.validation.Payload;
import java.lang.annotation.*;


@Documented
@Constraint(validatedBy = BinusEmailValidator.class)
@Target({ElementType.FIELD})
@Retention(RetentionPolicy.RUNTIME)
public @interface BinusEmail {
    String message() default "Email must be a valid @binus.ac.id address";  // THIS WAS MISSING
    Class<?>[] groups() default {};
    Class<? extends Payload>[] payload() default {};
}
