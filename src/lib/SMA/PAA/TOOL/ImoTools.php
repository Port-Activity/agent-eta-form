<?php
namespace SMA\PAA\TOOL;

class ImoTools
{
    public function isValidImo(int $imo): bool
    {
        $imoStr = strval($imo);
        $imoLength = strlen($imoStr);
        if ($imoLength !== 7) {
            throw new \InvalidArgumentException("IMO length should 7 digits or IMO should be 0");
        }
        #IMO number validity can be checked with check digit
        #Multiply each of the first six digits by a factor of 7 to 2 corresponding to their position.
        #The rightmost digit of this sum is the check digit.
        #Example 9074729: (9×7) + (0×6) + (7×5) + (4×4) + (7×3) + (2×2) = 139
        $imoCheckSum = 0;
        for ($i = 0; $i < $imoLength - 1; $i++) {
            $imoCheckSum += $imoStr[$i] * (7 - $i);
        }
        $imoCheckSumArray = str_split("$imoCheckSum");
        $checkDigit = end($imoCheckSumArray);

        if ($checkDigit !== $imoStr[6]) {
            throw new \InvalidArgumentException(
                "IMO number is not valid (automatic check calculation)"
            );
        }
        return true;
    }
}
