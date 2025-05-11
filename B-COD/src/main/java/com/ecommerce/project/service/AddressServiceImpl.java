package com.ecommerce.project.service;

import com.ecommerce.project.exceptions.ResourceNotFoundException;
import com.ecommerce.project.model.Address;
import com.ecommerce.project.model.User;
import com.ecommerce.project.payload.AddressDTO;
import com.ecommerce.project.repositories.AddressRepository;
import com.ecommerce.project.repositories.UserRepository;
import org.modelmapper.ModelMapper;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class AddressServiceImpl implements AddressService{
    @Autowired
    ModelMapper modelMapper;

    @Autowired
    AddressRepository addressRepository;

    @Autowired
    UserRepository userRepository;

    @Override
    public AddressDTO createAddress(AddressDTO addressDTO, User user) {
        Address address = modelMapper.map(addressDTO, Address.class);
        List<Address> addressList = user.getAddresses();
        addressList.add(address);
        user.setAddresses(addressList);

        address.setUser(user);
        Address savedAddress = addressRepository.save(address);

        AddressDTO savedAddressDTO = modelMapper.map(savedAddress, AddressDTO.class);
        System.out.println(savedAddressDTO);

        return modelMapper.map(savedAddress , AddressDTO.class);
    }

    @Override
    public List<AddressDTO> getAddress() {
        List<Address> addresses = addressRepository.findAll();
        List<AddressDTO> addressDTOS = addresses.stream()
                .map(address -> modelMapper.map(address, AddressDTO.class))
                .toList();
        return addressDTOS;
    }

    @Override
    public AddressDTO getAddressById(Long addressId) {
        Address address = addressRepository.findById(addressId)
                .orElseThrow(() ->new ResourceNotFoundException("Address","addressId",addressId));

        return modelMapper.map(address, AddressDTO.class);
    }

    @Override
    public List<AddressDTO> getUserAddress(User user) {
        List<Address> addresses = user.getAddresses();
        List<AddressDTO> addressDTOS = addresses.stream()
                .map(address -> modelMapper.map(address, AddressDTO.class))
                .toList();
        return addressDTOS;
    }

    @Override
    public AddressDTO updateAddressById(Long addressId, AddressDTO addressDTO) {
        Address address = addressRepository.findById(addressId)
                .orElseThrow(() ->new ResourceNotFoundException("Address","addressId",addressId));

        address.setCity(addressDTO.getCity());
        address.setCountry(addressDTO.getCountry());
        address.setState(addressDTO.getState());
        address.setStreet(addressDTO.getStreet());
        address.setBuildingName(addressDTO.getBuildingName());
        address.setPinCode(addressDTO.getPincode());

        Address updatedAddress = addressRepository.save(address);
        User user = address.getUser();
        user.getAddresses().removeIf(address1 -> address.getAddressId().equals(addressId));
        user.getAddresses().add(updatedAddress);

        userRepository.save(user);


        return modelMapper.map(updatedAddress, AddressDTO.class);
    }


}
